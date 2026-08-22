<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Tests\TestCase;

/**
 * The contribution guidelines are what a first-time contributor reads: GitHub looks for CONTRIBUTING.md at
 * the root, the full text lives on the documentation site in both languages, and the links between them must
 * point at files that exist. Task 352.
 */
class ContributingTest extends TestCase
{
    protected function root(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function contents(string $path): string
    {
        $this->assertFileExists($this->root().'/'.$path);

        return (string) file_get_contents($this->root().'/'.$path);
    }

    public function test_the_guidelines_ship_at_the_root_where_github_looks_for_them(): void
    {
        $contributing = $this->contents('CONTRIBUTING.md');

        $this->assertStringContainsString('composer test', $contributing);
        $this->assertStringContainsString('CHANGELOG.md', $contributing);
        $this->assertStringContainsString(
            'docs/contributing/contributing.md',
            $contributing,
            'the root file must point at the full guidelines'
        );
    }

    public function test_the_root_guidelines_link_only_to_files_that_exist(): void
    {
        preg_match_all('/]\((?!https?:|#|mailto:)([^)#]+)/', $this->contents('CONTRIBUTING.md'), $matches);
        $this->assertNotEmpty($matches[1]);

        $missing = [];
        foreach (array_unique($matches[1]) as $target) {
            if (! file_exists($this->root().'/'.trim($target))) {
                $missing[] = $target;
            }
        }

        $this->assertSame([], $missing, 'CONTRIBUTING.md links to missing files: '.implode(', ', $missing));
    }

    public function test_the_readme_and_the_governance_send_contributors_to_the_guidelines(): void
    {
        $this->assertStringContainsString('](CONTRIBUTING.md)', $this->contents('README.md'));
        $this->assertStringContainsString('](CONTRIBUTING.md)', $this->contents('GOVERNANCE.md'));
    }

    /**
     * The page is the contract with a contributor: it has to keep saying what a change carries, or the
     * checklist quietly stops matching what CI enforces.
     */
    public function test_both_documentation_pages_cover_the_essentials(): void
    {
        $expected = [
            'docs/contributing/contributing.md' => [
                'composer lint', 'composer test', 'griglia:docs-build --strict', 'CHANGELOG.md',
                ':agent', 'Unreleased', 'MIT', 'security',
            ],
            'docs/contributing/contributing.it.md' => [
                'composer lint', 'composer test', 'griglia:docs-build --strict', 'CHANGELOG.md',
                ':agent', 'Unreleased', 'MIT', 'sicurezza',
            ],
        ];

        foreach ($expected as $page => $needles) {
            $text = $this->contents($page);
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $text, "{$page} no longer mentions {$needle}");
            }
        }
    }

    public function test_the_guidelines_are_in_the_documentation_navigation(): void
    {
        $this->assertStringContainsString(
            'contributing/contributing.md',
            $this->contents('mkdocs.yml'),
            'the contributing page must stay in the documentation navigation'
        );
    }
}
