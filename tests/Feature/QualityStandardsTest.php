<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Tests\TestCase;

/**
 * The quality gate is made of files nobody opens until they break: the Composer scripts a contributor is told
 * to run, the workflow that runs them on GitHub, the Dependabot configuration and the page that documents the
 * bar. A typo in any of them is silent — Dependabot simply stops opening pull requests, a script stops
 * existing, the page starts describing a setup that is no longer there. Task 355.
 */
class QualityStandardsTest extends TestCase
{
    /** Every script the documentation promises, mapped to what it must run. */
    private const SCRIPTS = [
        'lint' => 'pint',
        'format' => 'pint',
        'test' => 'phpunit',
        'test:coverage' => 'coverage',
        'docs:check' => 'docs-generate',
        'docs:build' => 'docs-build',
    ];

    protected function root(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function contents(string $path): string
    {
        $this->assertFileExists($this->root().'/'.$path);

        return (string) file_get_contents($this->root().'/'.$path);
    }

    /** @return array<string, mixed> */
    protected function composerJson(): array
    {
        return json_decode($this->contents('composer.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_every_documented_composer_script_exists_and_does_what_its_name_says(): void
    {
        $scripts = $this->composerJson()['scripts'];

        foreach (self::SCRIPTS as $name => $needle) {
            $this->assertArrayHasKey($name, $scripts, "composer.json lost the {$name} script");
            $this->assertStringContainsString(
                $needle,
                implode(' ', (array) $scripts[$name]),
                "the {$name} script no longer runs {$needle}"
            );
        }
    }

    /**
     * `composer qa` is the single command a contributor is told to run: it has to be the same three checks CI
     * runs, in the same order, or the pull request is green locally and red on GitHub.
     */
    public function test_qa_runs_the_whole_gate_by_delegating_to_the_other_scripts(): void
    {
        $this->assertSame(
            ['@lint', '@test', '@docs:check'],
            $this->composerJson()['scripts']['qa'],
            'composer qa must be lint, then the suite, then the generated pages'
        );
    }

    public function test_every_script_is_described_for_composer_run_list(): void
    {
        $composer = $this->composerJson();
        $described = array_keys($composer['scripts-descriptions'] ?? []);

        foreach (array_keys($composer['scripts']) as $name) {
            $this->assertContains($name, $described, "the {$name} script has no description");
        }
    }

    public function test_the_workflow_keeps_the_jobs_the_quality_page_promises(): void
    {
        $workflow = $this->contents('.github/workflows/tests.yml');

        foreach (['prefer-lowest:', 'lint:', 'mysql:', 'security:'] as $job) {
            $this->assertStringContainsString("\n  {$job}", $workflow, "tests.yml lost the {$job} job");
        }

        $this->assertStringContainsString('composer lint', $workflow);
        $this->assertStringContainsString('composer audit', $workflow);
        $this->assertStringContainsString('--prefer-lowest', $workflow);
        $this->assertStringContainsString('griglia:docs-generate --check', $workflow);
    }

    /**
     * A workflow that can write is a workflow a malicious pull request would like to reach; and a run on
     * master that gets cancelled leaves the default branch without a verdict.
     */
    public function test_the_workflows_ask_for_no_more_permission_than_reading(): void
    {
        $tests = $this->contents('.github/workflows/tests.yml');

        $this->assertMatchesRegularExpression(
            '/^permissions:\n  contents: read\n/m',
            $tests,
            'tests.yml must declare a read-only token'
        );
        $this->assertStringContainsString('concurrency:', $tests, 'superseded runs should be cancelled');
        $this->assertStringContainsString(
            "cancel-in-progress: \${{ github.ref != 'refs/heads/master' }}",
            $tests,
            'a run on master must never be cancelled'
        );
    }

    public function test_dependabot_watches_the_three_ecosystems_without_touching_the_runtime_constraints(): void
    {
        $config = $this->contents('.github/dependabot.yml');

        $this->assertStringContainsString('version: 2', $config);
        $this->assertStringNotContainsString("\t", $config, 'YAML does not allow tabs');

        foreach (['composer', 'github-actions', 'npm'] as $ecosystem) {
            $this->assertStringContainsString(
                "package-ecosystem: {$ecosystem}",
                $config,
                "dependabot no longer watches {$ecosystem}"
            );
        }

        // Runtime constraints are deliberately wide (^12.0|^13.0) and belong to the host application:
        // Dependabot may bump the toolchain, never narrow what an installer is allowed to resolve.
        $this->assertStringContainsString('dependency-type: development', $config);
        $this->assertSame(
            3,
            substr_count($config, 'schedule:'),
            'every ecosystem needs its own schedule'
        );
        $this->assertGreaterThanOrEqual(3, substr_count($config, 'groups:'), 'updates are grouped, not one PR each');
    }

    public function test_the_quality_page_exists_in_both_languages_and_is_reachable(): void
    {
        foreach (['docs/contributing/quality.md', 'docs/contributing/quality.it.md'] as $page) {
            $text = $this->contents($page);
            $this->assertStringContainsString('composer qa', $text);
            $this->assertStringContainsString('phpstan-ignores.neon', $text);
        }

        $mkdocs = $this->contents('mkdocs.yml');
        $this->assertStringContainsString('contributing/quality.md', $mkdocs, 'the page is not in the navigation');
        $this->assertStringContainsString('Quality standards: Standard di qualità', $mkdocs, 'the Italian nav entry is missing');

        $this->assertStringContainsString('docs/contributing/quality.md', $this->contents('CONTRIBUTING.md'));
        foreach (['docs/contributing/contributing.md', 'docs/contributing/development.md'] as $page) {
            $this->assertStringContainsString('quality.md', $this->contents($page), "{$page} does not link the quality page");
        }
    }

    /** The page lists the commands: a script renamed without opening the page would leave it lying. */
    public function test_the_quality_page_documents_every_composer_script(): void
    {
        foreach (['docs/contributing/quality.md', 'docs/contributing/quality.it.md'] as $page) {
            $text = $this->contents($page);

            foreach (array_keys($this->composerJson()['scripts']) as $name) {
                $this->assertStringContainsString("composer {$name}", $text, "{$page} does not document composer {$name}");
            }
        }
    }

    /**
     * The static analysis policy is written down: level 5, no baseline, and an exception list that cannot rot
     * — an entry that no longer matches fails the analysis instead of staying there forever.
     */
    public function test_the_static_analysis_policy_is_what_the_page_says_it_is(): void
    {
        $phpstan = $this->contents('phpstan.neon');
        $ignores = $this->contents('phpstan-ignores.neon');

        $this->assertMatchesRegularExpression('/^\s+level: 5$/m', $phpstan);
        $this->assertStringNotContainsString('baseline', $phpstan, 'a baseline hides debt behind a green run');
        $this->assertStringContainsString('reportUnmatchedIgnoredErrors: true', $ignores);

        foreach ($this->ignoredPaths($ignores) as $path) {
            $this->assertFileExists($this->root().'/'.$path, "phpstan-ignores.neon still excuses {$path}, which no longer exists");
        }
    }

    /** @return list<string> the concrete (non-wildcard) paths excused in the ignore list */
    private function ignoredPaths(string $ignores): array
    {
        preg_match_all('/path: ([^\s,}]+)/', $ignores, $matches);

        return array_values(array_filter(
            array_unique($matches[1]),
            fn (string $path): bool => ! str_contains($path, '*')
        ));
    }

    /**
     * Italian words that no comment may contain: the package is meant to be reused by people who do not read
     * Italian, so code, host scripts, views and stylesheets are commented in English (task 627). The user-facing
     * strings are a different matter — those live in resources/lang and are translated on purpose.
     * The list keeps out words English shares with Italian («serve», «come», «per»): a guard that cries wolf
     * gets switched off.
     */
    private const ITALIAN_WORDS = ['della', 'delle', 'degli', 'nella', 'nello', 'alla', 'allo', 'agli', 'dalla',
        'sulla', 'questo', 'questa', 'quello', 'quella', 'quando', 'perché', 'oppure', 'altrimenti', 'anche',
        'soltanto', 'così', 'ogni', 'tutti', 'tutte', 'senza', 'niente', 'invece', 'mentre', 'quindi', 'però',
        'viene', 'vengono', 'devono', 'utente', 'impostazioni', 'cartella', 'sorgente'];

    /** Directories whose comments are checked, and the extensions worth reading in each. */
    private const COMMENTED_TREES = [
        'src' => ['php'],
        'scripts' => ['py', 'json'],
        'tests' => ['php'],
        'database' => ['php'],
        'config' => ['php'],
        'routes' => ['php'],
        'resources/js' => ['js'],
        'resources/css' => ['css'],
        'resources/views' => ['php'],
    ];

    public function test_the_code_and_the_host_scripts_are_commented_in_english(): void
    {
        $offenders = [];
        foreach (self::COMMENTED_TREES as $tree => $extensions) {
            foreach ($this->filesIn($tree, $extensions) as $file) {
                foreach ($this->commentLines($file) as $number => $line) {
                    foreach (self::ITALIAN_WORDS as $word) {
                        if (preg_match('/\b'.preg_quote($word, '/').'\b/iu', $line)) {
                            $offenders[] = substr($file, strlen($this->root()) + 1).':'.$number.' — '.trim($line);

                            continue 2;
                        }
                    }
                }
            }
        }

        $this->assertSame([], $offenders, "these comments are still in Italian:\n".implode("\n", $offenders));
    }

    /**
     * @param  list<string>  $extensions
     * @return list<string> absolute paths
     */
    private function filesIn(string $tree, array $extensions): array
    {
        $base = $this->root().'/'.$tree;
        $this->assertDirectoryExists($base);
        $files = [];
        $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($walk as $file) {
            if ($file->isFile() && in_array($file->getExtension(), $extensions, true)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Comment (and docstring) lines of a file, keyed by line number. Deliberately literal: a line counts as a
     * comment when it opens with a marker, or when it sits inside a Python docstring — a JSON "description" is
     * read whole, because that file is a catalogue of prose.
     *
     * @return array<int, string>
     */
    private function commentLines(string $file): array
    {
        $json = str_ends_with($file, '.json');
        $python = str_ends_with($file, '.py');
        $lines = [];
        $inDocstring = false;
        foreach (explode("\n", (string) file_get_contents($file)) as $i => $line) {
            $trimmed = ltrim($line);
            $isComment = $json
                || ($inDocstring && $python)
                || (bool) preg_match('~^(//|#|\*|/\*|\{\{--|<!--)~', $trimmed);
            if ($python && substr_count($line, '"""') === 1) {
                $inDocstring = ! $inDocstring;
                $isComment = true;
            }
            if ($isComment && $trimmed !== '') {
                $lines[$i + 1] = $line;
            }
        }

        return $lines;
    }
}
