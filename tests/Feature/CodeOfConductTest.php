<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Tests\TestCase;

/**
 * The code of conduct is the Contributor Covenant 2.1, adopted as it is: GitHub looks for CODE_OF_CONDUCT.md
 * at the root, the covenant text must stay unmodified apart from the reporting contact, and the documentation
 * page that explains it lives on the site in both languages. Task 353.
 */
class CodeOfConductTest extends TestCase
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

    public function test_the_covenant_ships_at_the_root_where_github_looks_for_it(): void
    {
        $conduct = $this->contents('CODE_OF_CONDUCT.md');

        $this->assertStringStartsWith(
            '<!-- --8<-- [start:covenant] -->',
            $conduct,
            'the documentation page includes the covenant through this snippet marker'
        );

        foreach ([
            '# Contributor Covenant Code of Conduct',
            '## Our Pledge',
            '## Our Standards',
            '## Enforcement Responsibilities',
            '## Scope',
            '## Enforcement',
            '## Enforcement Guidelines',
            '## Attribution',
            'version 2.1',
        ] as $needle) {
            $this->assertStringContainsString($needle, $conduct, "the covenant no longer contains {$needle}");
        }

        $this->assertStringContainsString('<!-- --8<-- [end:covenant] -->', $conduct);
    }

    /**
     * A code of conduct nobody can report to is decoration: the placeholder of the template must be replaced,
     * and the reader must be told where the address is.
     */
    public function test_the_reporting_contact_is_filled_in(): void
    {
        $conduct = $this->contents('CODE_OF_CONDUCT.md');

        $this->assertStringNotContainsString('[INSERT CONTACT METHOD]', $conduct);
        $this->assertStringContainsString('composer.json', $conduct);
        $this->assertStringContainsString('SECURITY.md', $conduct, 'a vulnerability is not a conduct report');
    }

    public function test_the_root_document_links_only_to_files_that_exist(): void
    {
        preg_match_all('/]\((?!https?:|#|mailto:)([^)#]+)/', $this->contents('CODE_OF_CONDUCT.md'), $matches);
        $this->assertNotEmpty($matches[1]);

        $missing = [];
        foreach (array_unique($matches[1]) as $target) {
            if (! file_exists($this->root().'/'.trim($target))) {
                $missing[] = $target;
            }
        }

        $this->assertSame([], $missing, 'CODE_OF_CONDUCT.md links to missing files: '.implode(', ', $missing));
    }

    public function test_the_project_documents_send_the_reader_to_the_code_of_conduct(): void
    {
        foreach (['README.md', 'CONTRIBUTING.md', 'GOVERNANCE.md'] as $file) {
            $this->assertStringContainsString(
                '](CODE_OF_CONDUCT.md)',
                $this->contents($file),
                "{$file} must link the code of conduct"
            );
        }

        foreach ([
            'docs/contributing/contributing.md',
            'docs/contributing/contributing.it.md',
            'docs/contributing/governance.md',
            'docs/contributing/governance.it.md',
        ] as $page) {
            $this->assertStringContainsString(
                '](code-of-conduct.md)',
                $this->contents($page),
                "{$page} must link the code of conduct page"
            );
        }
    }

    /**
     * The page is bilingual and must keep saying the things a template cannot say: where the covenant applies,
     * how a report is answered, and the full text itself.
     */
    public function test_both_documentation_pages_cover_the_essentials(): void
    {
        $expected = [
            'docs/contributing/code-of-conduct.md' => [
                'Contributor Covenant', '2.1', 'composer.json', 'three working days',
                '--8<-- "CODE_OF_CONDUCT.md:covenant"', 'contributing.md#how-we-talk-to-each-other',
            ],
            'docs/contributing/code-of-conduct.it.md' => [
                'Contributor Covenant', '2.1', 'composer.json', 'tre giorni lavorativi',
                '--8<-- "CODE_OF_CONDUCT.md:covenant"', 'contributing.md#come-ci-si-parla',
            ],
        ];

        foreach ($expected as $page => $needles) {
            $text = $this->contents($page);
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $text, "{$page} no longer mentions {$needle}");
            }
        }
    }

    public function test_the_page_is_in_the_documentation_navigation(): void
    {
        $mkdocs = $this->contents('mkdocs.yml');

        $this->assertStringContainsString('contributing/code-of-conduct.md', $mkdocs);
        $this->assertStringContainsString(
            'Code of conduct: Codice di condotta',
            $mkdocs,
            'the Italian navigation needs the translated title'
        );
    }
}
