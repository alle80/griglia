<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Tests\TestCase;

/**
 * The two pages that answer «how is this put together?» and «where is it going?». Both are written by hand,
 * so what they claim can drift away from the code: these tests pin the drift that matters — the state
 * machine of the cycle, the tables the migrations really create, the decisions taken about the scope — and
 * check that both pages exist in both languages and are reachable. Task 626.
 */
class ArchitectureAndRoadmapTest extends TestCase
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

    /** The mermaid block of a page, without its fences. */
    protected function diagram(string $markdown): string
    {
        $this->assertMatchesRegularExpression('/```mermaid\n(.+?)```/s', $markdown, 'the page must draw the cycle');
        preg_match('/```mermaid\n(.+?)```/s', $markdown, $matches);

        return $matches[1];
    }

    public function test_the_architecture_page_draws_the_whole_cycle(): void
    {
        foreach ([
            'docs/architecture.md' => ['waiting', 'open_to_work', 'working', 'question', 'paused', 'in_review', 'done'],
            'docs/architecture.it.md' => ['in_attesa', 'da_fare', 'in_lavorazione', 'domanda', 'in_pausa', 'in_revisione', 'fatto'],
        ] as $page => $states) {
            $diagram = $this->diagram($this->contents($page));

            $this->assertStringContainsString('stateDiagram', $diagram);
            foreach ($states as $state) {
                $this->assertStringContainsString($state, $diagram, "$page must draw the $state state");
            }

            // Every command that moves the machine has to appear on an arrow, or the drawing is a decoration
            foreach (['--take', '--ask', '--pause', '--done', '--approve', '--request-changes'] as $option) {
                $this->assertStringContainsString($option, $diagram, "$page must show what $option does");
            }
        }
    }

    public function test_the_architecture_page_names_the_tables_that_really_exist(): void
    {
        $migrations = implode("\n", array_map(
            fn ($file) => (string) file_get_contents($file),
            glob($this->root().'/database/migrations/*.php') ?: [],
        ));
        preg_match_all("/Schema::create\('(\w+)'/", $migrations, $matches);
        $tables = array_unique($matches[1]);
        $this->assertNotEmpty($tables);

        foreach (['docs/architecture.md', 'docs/architecture.it.md'] as $page) {
            $architecture = $this->contents($page);
            foreach ($tables as $table) {
                $this->assertStringContainsString("`$table`", $architecture, "$page must place the $table table");
            }

            // …and the directories the code is actually split into
            foreach (['src/Models/', 'src/Livewire/', 'src/Console/', 'src/Domain/', 'src/Support/',
                'src/Settings/', 'src/Events/', 'src/Http/', 'src/Notifications/', 'src/Ai/'] as $dir) {
                $this->assertDirectoryExists($this->root().'/'.$dir);
                $this->assertStringContainsString("`$dir`", $architecture, "$page must place $dir");
            }
        }
    }

    public function test_the_roadmap_keeps_the_three_answers_apart(): void
    {
        foreach ([
            'docs/roadmap.md' => ['## Where it is today', '## What comes next', '## Out of scope by choice'],
            'docs/roadmap.it.md' => ['## Dove siamo oggi', '## Cosa arriva', '## Fuori perimetro per scelta'],
        ] as $page => $sections) {
            $roadmap = $this->contents($page);
            foreach ($sections as $section) {
                $this->assertStringContainsString($section, $roadmap, "$page must keep the «{$section}» section");
            }

            // The decisions that close a branch: whoever reads the page must find them without asking
            foreach (['MCP', 'webhook', 'PWA', 'griglia:doctor', 'griglia:install', 'griglia:export', 'PHPStan'] as $subject) {
                $this->assertStringContainsString($subject, $roadmap, "$page must say where $subject stands");
            }

            $this->assertMatchesRegularExpression(
                '/\]\((contributing\/governance\.md|contributing\/governance\.it\.md)\)/',
                $roadmap,
                "$page must say who decides ($page)"
            );
        }
    }

    public function test_both_pages_are_in_the_navigation_and_in_the_readme(): void
    {
        $mkdocs = $this->contents('mkdocs.yml');
        $this->assertStringContainsString('Architecture: architecture.md', $mkdocs);
        $this->assertStringContainsString('Roadmap: roadmap.md', $mkdocs);
        $this->assertStringContainsString('Architecture: Architettura', $mkdocs, 'the Italian site needs the translated title');
        $this->assertStringContainsString('name: mermaid', $mkdocs, 'the cycle diagram needs the mermaid fence');

        $readme = $this->contents('README.md');
        $this->assertStringContainsString('docs/architecture.md', $readme);
        $this->assertStringContainsString('docs/roadmap.md', $readme);
    }

    public function test_the_two_pages_link_only_to_files_that_exist(): void
    {
        foreach (['docs/architecture.md', 'docs/architecture.it.md', 'docs/roadmap.md', 'docs/roadmap.it.md'] as $page) {
            preg_match_all('/]\((?!https?:|#|mailto:)([^)#]+)/', $this->contents($page), $matches);
            $this->assertNotEmpty($matches[1], "$page must link to the pages it summarises");

            $missing = [];
            foreach (array_unique($matches[1]) as $target) {
                if (! file_exists($this->root().'/docs/'.$target)) {
                    $missing[] = $target;
                }
            }

            $this->assertSame([], $missing, "$page links to missing files: ".implode(', ', $missing));
        }
    }
}
