<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Tests\TestCase;

/**
 * A release is a tag plus a changelog section, and everything else is generated from those two. This test
 * guards the generator and the pieces that would fail silently: a changelog heading in the wrong shape, a
 * link block that stopped matching, a workflow that no longer reacts to tags or no longer has the permission
 * to publish. None of it shows up until a release comes out wrong. Task 356.
 */
class ReleaseProcessTest extends TestCase
{
    private const SCRIPT = '.github/scripts/changelog-notes.php';

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function contents(string $path): string
    {
        $this->assertFileExists($this->root().'/'.$path);

        return (string) file_get_contents($this->root().'/'.$path);
    }

    /** Runs the extractor the way the workflow does: a bare PHP process, no autoloader. */
    private function notes(string ...$arguments): array
    {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->root().'/'.self::SCRIPT);

        foreach ($arguments as $argument) {
            $command .= ' '.escapeshellarg($argument);
        }

        $output = [];
        $status = 0;
        exec($command.' 2>/dev/null', $output, $status);

        return [$status, implode("\n", $output)];
    }

    /** @return list<string> released versions, newest first */
    private function releasedVersions(): array
    {
        preg_match_all('/^## \[([0-9][^\]]*)\]/m', $this->contents('CHANGELOG.md'), $matches);

        return $matches[1];
    }

    public function test_the_changelog_headings_all_carry_a_version_and_a_date(): void
    {
        foreach (explode("\n", $this->contents('CHANGELOG.md')) as $line) {
            if (! str_starts_with($line, '## ')) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/^## \[(Unreleased|\d+\.\d+\.\d+)\](\s-\s\d{4}-\d{2}-\d{2})?$/',
                rtrim($line),
                "a changelog heading is not in Keep a Changelog shape: {$line}"
            );

            if (! str_contains($line, 'Unreleased')) {
                $this->assertStringContainsString(' - ', $line, "a released version has no date: {$line}");
            }
        }
    }

    /**
     * A release cut without writing its section leaves a tag nobody can read. It happened twice (0.12.1 and
     * 0.54.1, written back in afterwards), and a hole in the patch sequence is the visible half of it.
     */
    public function test_the_released_versions_run_newest_first_without_a_hole(): void
    {
        $versions = array_map(
            fn (string $version): array => array_map('intval', explode('.', $version)),
            $this->releasedVersions()
        );

        $oldestFirst = array_reverse($versions);

        foreach ($oldestFirst as $index => $version) {
            $next = $oldestFirst[$index + 1] ?? null;

            if ($next === null) {
                continue;
            }

            $this->assertGreaterThan($version, $next, 'the changelog is not ordered newest first');

            if ($next[0] === $version[0] && $next[1] === $version[1]) {
                $this->assertSame(
                    $version[2] + 1,
                    $next[2],
                    sprintf('no section between %s and %s', implode('.', $version), implode('.', $next))
                );
            }
        }
    }

    public function test_the_extractor_returns_the_section_of_the_asked_version(): void
    {
        $versions = $this->releasedVersions();
        $newest = $versions[0];

        [$status, $notes] = $this->notes('v'.$newest);

        $this->assertSame(0, $status, 'the extractor failed on the newest released version');
        $this->assertNotSame('', trim($notes), "the notes of {$newest} are empty");
        $this->assertStringNotContainsString('## [', $notes, 'the notes leaked into the next version');

        // The oldest release is the one the link block sits against: it is where a naive reader swallows it.
        [$status, $oldest] = $this->notes(end($versions));

        $this->assertSame(0, $status);
        $this->assertDoesNotMatchRegularExpression(
            '/^\[[^\]]+\]:\s+http/m',
            $oldest,
            'the notes swallowed the link block that closes the changelog'
        );
    }

    public function test_the_extractor_knows_the_previous_version_and_the_date(): void
    {
        $versions = $this->releasedVersions();

        [$status, $previous] = $this->notes($versions[0], '--previous');
        $this->assertSame(0, $status);
        $this->assertSame($versions[1], trim($previous));

        [, $first] = $this->notes(end($versions), '--previous');
        $this->assertSame('', trim($first), 'the oldest release cannot have a predecessor');

        [, $date] = $this->notes($versions[0], '--date');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', trim($date));
    }

    /** An unknown version must stop the release workflow, not publish an empty release. */
    public function test_a_version_without_a_section_fails(): void
    {
        [$status, $notes] = $this->notes('99.99.99');

        $this->assertSame(3, $status, 'a missing version has to fail with its own exit code');
        $this->assertSame('', trim($notes));
    }

    public function test_the_changelog_link_block_matches_the_generated_one(): void
    {
        [$status, $generated] = $this->notes('--links');
        $this->assertSame(0, $status);

        $lines = explode("\n", rtrim($this->contents('CHANGELOG.md')));
        $block = [];

        while ($lines !== [] && preg_match('/^\[[^\]]+\]:\s+http/', (string) end($lines))) {
            array_unshift($block, (string) array_pop($lines));
        }

        $this->assertSame(
            trim($generated),
            implode("\n", $block),
            'the link block at the end of CHANGELOG.md is stale: regenerate it with '
            .'`php '.self::SCRIPT.' --links`'
        );
    }

    public function test_every_released_version_has_a_link_definition(): void
    {
        $changelog = $this->contents('CHANGELOG.md');

        $this->assertStringContainsString('[Unreleased]: ', $changelog);

        foreach ($this->releasedVersions() as $version) {
            $this->assertStringContainsString("[{$version}]: https://", $changelog, "{$version} has no link");
        }
    }

    public function test_the_release_workflow_publishes_from_the_changelog(): void
    {
        $workflow = $this->contents('.github/workflows/release.yml');

        $this->assertStringContainsString('tags:', $workflow, 'the workflow no longer reacts to a tag');
        $this->assertStringContainsString("'v[0-9]+.[0-9]+.[0-9]+'", $workflow);
        $this->assertStringContainsString('workflow_dispatch:', $workflow, 'a published tag cannot be redone');
        $this->assertStringContainsString('contents: write', $workflow, 'it cannot create a release');
        $this->assertStringContainsString(self::SCRIPT, $workflow, 'the notes no longer come from the changelog');
        $this->assertStringContainsString('gh release create', $workflow);
        $this->assertStringContainsString('gh release edit', $workflow, 'rerunning it has to refresh the notes');
    }

    /** The read-only workflows must stay read-only: only the release job may write. */
    public function test_the_other_workflows_do_not_gain_write_access(): void
    {
        $this->assertStringContainsString('contents: read', $this->contents('.github/workflows/tests.yml'));
    }

    public function test_the_policy_is_documented_in_both_languages_and_in_the_navigation(): void
    {
        foreach (['docs/contributing/releases.md', 'docs/contributing/releases.it.md'] as $page) {
            $body = $this->contents($page);

            $this->assertStringContainsString('0.89.0', $body, "{$page} does not show how to pin a version");
            $this->assertStringContainsString(self::SCRIPT, $body, "{$page} does not name the generator");
        }

        $navigation = $this->contents('mkdocs.yml');
        $this->assertStringContainsString('contributing/releases.md', $navigation, 'the page is not in the nav');
        $this->assertStringContainsString('Versioning and releases: Versioni e rilasci', $navigation);
    }
}
