<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Tests\TestCase;

/**
 * The FAQ and the glossary are the two pages a newcomer reads before anything else: they must exist in both
 * languages, answer the questions people actually ask, define the words the interface uses (starting with
 * «Ingredient», which is a historical name), and be reachable from the site navigation and the README.
 * Task 625.
 */
class FaqAndGlossaryTest extends TestCase
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

    /** @return list<string> the headings of one level, without their marker */
    protected function headings(string $markdown, string $level): array
    {
        preg_match_all('/^'.$level.' (.+)$/m', $markdown, $matches);

        return $matches[1];
    }

    public function test_the_faq_answers_the_questions_that_are_actually_asked(): void
    {
        foreach (['docs/faq.md' => 'en', 'docs/faq.it.md' => 'it'] as $page => $language) {
            $faq = $this->contents($page);
            $questions = $this->headings($faq, '###');

            $this->assertGreaterThanOrEqual(
                8,
                count($questions),
                "$page must keep at least the eight most frequent questions"
            );

            foreach ([
                'griglia:check',            // nothing to do at the first check
                'GRIGLIA_AGENT_LIST',       // …because the request is in another list
                'GRIGLIA_MODE=local',       // local and server modes
                'GRIGLIA_ASSETS=vite',      // Node, Vite and Tailwind
                'notify_on_question',       // how the board reaches you
                'agent/workers.md',         // unattended work
                'agent/stats.md',           // tokens and cost
            ] as $subject) {
                $this->assertStringContainsString($subject, $faq, "$page must cover $subject");
            }

            $this->assertMatchesRegularExpression(
                '/\]\(glossary\.md\)/',
                $faq,
                "$page must send the reader to the glossary ($language)"
            );
        }

        $this->assertSame(
            count($this->headings($this->contents('docs/faq.md'), '###')),
            count($this->headings($this->contents('docs/faq.it.md'), '###')),
            'the two languages must answer the same questions'
        );
    }

    public function test_the_glossary_defines_the_words_the_board_uses(): void
    {
        $terms = [
            'docs/glossary.md' => ['Sub-task', 'Ingredient', 'Agent', 'Worker', 'Plan', 'Skill',
                'Context blocks', 'Theme', 'Style', 'Mode', 'State', 'Archive', 'Agent list'],
            'docs/glossary.it.md' => ['Sotto-task', 'Ingredient', 'Agente', 'Worker', 'Piano', 'Skill',
                'Blocchi di contesto', 'Tema', 'Stile', 'Modalità', 'Stato', 'Archivio', 'Lista dell\'agente'],
        ];

        foreach ($terms as $page => $expected) {
            $glossary = $this->contents($page);
            foreach ($expected as $term) {
                $this->assertStringContainsString("**$term**", $glossary, "$page must define «{$term}»");
            }
        }

        $this->assertStringContainsString(
            'ingredients',
            $this->contents('docs/glossary.md'),
            'the historical name must be traceable down to the table'
        );
    }

    public function test_the_glossary_shows_every_state_with_its_icon(): void
    {
        foreach (['docs/glossary.md', 'docs/glossary.it.md'] as $page) {
            $glossary = $this->contents($page);

            foreach (['waiting', 'open', 'working', 'paused', 'question', 'stop', 'done'] as $state) {
                $icon = "images/state-$state.svg";
                $this->assertStringContainsString($icon, $glossary, "$page must show the $state dot");
                $this->assertFileExists($this->root().'/docs/'.$icon);
            }
        }
    }

    public function test_both_pages_are_in_the_navigation_and_in_the_readme(): void
    {
        $mkdocs = $this->contents('mkdocs.yml');
        $this->assertStringContainsString('FAQ: faq.md', $mkdocs);
        $this->assertStringContainsString('Glossary: glossary.md', $mkdocs);
        $this->assertStringContainsString('FAQ: Domande frequenti', $mkdocs, 'the Italian site needs the translated title');
        $this->assertStringContainsString('Glossary: Glossario', $mkdocs);

        $readme = $this->contents('README.md');
        $this->assertStringContainsString('docs/faq.md', $readme);
        $this->assertStringContainsString('docs/glossary.md', $readme);
    }

    public function test_the_two_pages_link_only_to_files_that_exist(): void
    {
        foreach (['docs/faq.md', 'docs/faq.it.md', 'docs/glossary.md', 'docs/glossary.it.md'] as $page) {
            preg_match_all('/]\((?!https?:|#|mailto:)([^)#]+)/', $this->contents($page), $matches);
            $this->assertNotEmpty($matches[1], "$page must link to the long versions");

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
