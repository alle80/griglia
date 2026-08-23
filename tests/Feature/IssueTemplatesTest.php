<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Tests\TestCase;

/**
 * GitHub reads the issue forms and the pull request template from `.github/`: a typo in a form is silent —
 * the form simply stops being offered — so the structure, the labels and the questions are guarded here.
 * The questions must keep matching the Contributing page, which is what a human is told to read. Task 354.
 */
class IssueTemplatesTest extends TestCase
{
    /** The labels that exist on the repository; GitHub drops the ones that do not. */
    private const KNOWN_LABELS = ['accessibility', 'bug', 'documentation', 'duplicate', 'enhancement', 'good first issue', 'help wanted', 'invalid', 'question', 'wontfix'];

    private const FORMS = [
        '1-bug.yml' => 'bug',
        '2-idea.yml' => 'enhancement',
        '3-documentation.yml' => 'documentation',
        '4-question.yml' => 'question',
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

    protected function form(string $file): string
    {
        return $this->contents('.github/ISSUE_TEMPLATE/'.$file);
    }

    public function test_every_form_declares_a_name_a_description_and_a_body(): void
    {
        foreach (array_keys(self::FORMS) as $file) {
            $form = $this->form($file);

            foreach (['name:', 'description:', 'labels:', 'body:'] as $key) {
                $this->assertMatchesRegularExpression(
                    '/^'.preg_quote($key, '/').'/m',
                    $form,
                    "{$file} has no top-level {$key}"
                );
            }

            $this->assertStringNotContainsString("\t", $form, "{$file} contains a tab: YAML does not allow it");
        }
    }

    /**
     * A label GitHub does not know is dropped without a word, and the issue lands unlabelled in a repository
     * that triages by label.
     */
    public function test_every_form_applies_a_label_that_exists_on_the_repository(): void
    {
        foreach (self::FORMS as $file => $expected) {
            preg_match('/^labels: \["([^"]+)"\]$/m', $this->form($file), $matches);

            $this->assertNotEmpty($matches, "{$file} must declare its labels as a one-line list");
            $this->assertSame($expected, $matches[1], "{$file} should be labelled {$expected}");
            $this->assertContains($matches[1], self::KNOWN_LABELS, "the label {$matches[1]} does not exist on the repository");
        }
    }

    public function test_every_input_of_every_form_is_addressable_and_of_a_known_type(): void
    {
        foreach (array_keys(self::FORMS) as $file) {
            $form = $this->form($file);

            preg_match_all('/^  - type: (\w+)$/m', $form, $types);
            $this->assertNotEmpty($types[1], "{$file} has no body element");

            foreach ($types[1] as $type) {
                $this->assertContains(
                    $type,
                    ['markdown', 'input', 'textarea', 'dropdown', 'checkboxes'],
                    "{$file} uses the unknown element type {$type}"
                );
            }

            preg_match_all('/^    id: ([\w-]+)$/m', $form, $ids);
            $inputs = count(array_filter($types[1], fn (string $type): bool => $type !== 'markdown'));
            $this->assertCount($inputs, $ids[1], "{$file}: every element but the markdown ones needs an id");
            $this->assertSame($ids[1], array_unique($ids[1]), "{$file} repeats an id");

            $this->assertSame(
                count($types[1]),
                substr_count($form, "\n    attributes:"),
                "{$file}: every element needs an attributes block"
            );
        }
    }

    /**
     * The bug form is the one that decides whether a report can be reproduced: it asks for the same list the
     * Contributing page asks for in prose.
     */
    public function test_the_bug_form_asks_what_a_reproducible_report_needs(): void
    {
        $form = $this->form('1-bug.yml');

        foreach ([
            'id: what-happened',
            'id: reproduction',
            'id: griglia-version',
            'id: laravel-php',
            'id: mode',
            'id: logs',
            'composer show alle80/griglia',
            'griglia:check --all',
            'render: shell',
        ] as $needle) {
            $this->assertStringContainsString($needle, $form, "the bug form no longer asks for {$needle}");
        }

        foreach (['server', 'local'] as $mode) {
            $this->assertMatchesRegularExpression('/^        - '.$mode.' /m', $form, "the mode dropdown lost {$mode}");
        }

        $this->assertSame(
            5,
            substr_count($form, "    validations:\n      required: true"),
            'the bug form should require the story, the reproduction, the versions and the mode'
        );
    }

    /**
     * Scope refuses more proposals than quality does: the form asks for it before the code is written, and
     * makes the two constraints of the project explicit.
     */
    public function test_the_idea_form_asks_for_the_scope_and_the_two_constraints(): void
    {
        $form = $this->form('2-idea.yml');

        foreach ([
            'id: problem',
            'id: proposal',
            'id: ruled-out',
            'id: scope',
            'contributing/governance/#scope',
            'does not hardcode an agent, a model or a provider',
            'optional and off by default',
        ] as $needle) {
            $this->assertStringContainsString($needle, $form, "the idea form no longer asks for {$needle}");
        }
    }

    public function test_the_documentation_form_knows_that_the_site_is_bilingual_and_partly_generated(): void
    {
        $form = $this->form('3-documentation.yml');

        foreach (['English', 'Italian', 'Both', 'griglia:docs-generate'] as $needle) {
            $this->assertStringContainsString($needle, $form, "the documentation form no longer mentions {$needle}");
        }
    }

    public function test_the_question_form_sends_the_reader_to_the_faq_first(): void
    {
        $form = $this->form('4-question.yml');

        $this->assertStringContainsString('/faq/', $form);
        $this->assertStringContainsString('operations/troubleshooting/', $form);
        $this->assertStringContainsString('id: versions', $form, 'a question without versions cannot be answered');
    }

    public function test_every_form_asks_for_the_code_of_conduct(): void
    {
        foreach (array_keys(self::FORMS) as $file) {
            $form = $this->form($file);

            $this->assertStringContainsString('id: conduct', $form, "{$file} does not mention the code of conduct");
            $this->assertStringContainsString('blob/master/CODE_OF_CONDUCT.md', $form, "{$file} must link the covenant");
        }
    }

    /**
     * Blank issues would make the forms decorative; a vulnerability and a first question need a door that is
     * not a public bug report.
     */
    public function test_the_configuration_closes_the_blank_issue_and_opens_the_other_doors(): void
    {
        $config = $this->contents('.github/ISSUE_TEMPLATE/config.yml');

        $this->assertStringContainsString('blank_issues_enabled: false', $config);
        $this->assertStringContainsString('security/advisories/new', $config, 'a vulnerability is reported privately');
        $this->assertStringContainsString('https://alle80.github.io/griglia/', $config);

        $names = substr_count($config, "\n  - name: ");
        $this->assertSame($names, substr_count($config, "\n    url: "), 'every contact link needs a url');
        $this->assertSame($names, substr_count($config, "\n    about: "), 'every contact link needs an about');
        $this->assertGreaterThanOrEqual(2, $names);
    }

    /**
     * The checklist of the pull request template and the one on the Contributing page are the same list: a
     * contributor who ticks the first must not discover a sixth requirement in the review.
     */
    public function test_the_pull_request_template_carries_the_contribution_checklist(): void
    {
        $template = $this->contents('.github/PULL_REQUEST_TEMPLATE.md');

        foreach (['## The problem', '## What changed', '## How you tested it', '## Checklist'] as $heading) {
            $this->assertStringContainsString($heading, $template, "the template lost the {$heading} section");
        }

        $this->assertStringContainsString('Closes #', $template, 'the template must ask for the issue it closes');

        foreach ([
            'composer lint',
            'composer test',
            'griglia:docs-build --strict',
            'a test that fails without the change',
            'English **and** Italian',
            '*Unreleased*',
            'no hardcoded agent name',
        ] as $item) {
            $this->assertStringContainsString($item, $template, "the checklist lost «{$item}»");
        }

        $this->assertSame(
            substr_count($this->contents('docs/contributing/contributing.md'), '- [ ] '),
            7,
            'the Contributing page still carries the seven-item checklist the template repeats'
        );
    }

    /**
     * The board is vendor-neutral: no template may greet a contributor with the name of the agent the
     * maintainer happens to use.
     */
    public function test_no_template_hardcodes_an_agent(): void
    {
        $files = array_map(
            fn (string $file): string => '.github/ISSUE_TEMPLATE/'.$file,
            [...array_keys(self::FORMS), 'config.yml']
        );
        $files[] = '.github/PULL_REQUEST_TEMPLATE.md';

        foreach ($files as $file) {
            foreach (['Claude', 'Codex', 'Copilot', 'ChatGPT', 'Gemini', 'Cursor'] as $product) {
                $this->assertStringNotContainsString(
                    $product,
                    $this->contents($file),
                    "{$file} names {$product}: the templates stay vendor-neutral"
                );
            }
        }
    }

    public function test_the_documentation_tells_the_contributor_the_forms_exist(): void
    {
        foreach ([
            'docs/contributing/contributing.md' => ['New issue', 'Blank issues are disabled', '.github/PULL_REQUEST_TEMPLATE.md'],
            'docs/contributing/contributing.it.md' => ['New issue', 'issue vuote sono disattivate', '.github/PULL_REQUEST_TEMPLATE.md'],
            'CONTRIBUTING.md' => ['.github/ISSUE_TEMPLATE', '.github/PULL_REQUEST_TEMPLATE.md'],
        ] as $page => $needles) {
            $text = $this->contents($page);
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $text, "{$page} no longer mentions {$needle}");
            }
        }
    }
}
