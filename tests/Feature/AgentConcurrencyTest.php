<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Models\Checklist;
use Alle80\Griglia\Models\Todo;
use Alle80\Griglia\Tests\TestCase;

/** Two agents on the same board must not step on each other: ownership guard, per-agent 🆕 baseline, busy line. */
class AgentConcurrencyTest extends TestCase
{
    private Checklist $list;

    protected function setUp(): void
    {
        parent::setUp();
        config(['griglia.agents' => 'claude:Claude Code,codex:Codex CLI']);
        $user = $this->actingAsUser();
        $this->list = Checklist::create(['name' => 'dev', 'user_id' => $user->id]);
    }

    private function todo(string $title, array $attrs = []): Todo
    {
        return Todo::create(['title' => $title, 'order' => 1, 'checklist_id' => $this->list->id, 'open_to_work' => true] + $attrs);
    }

    public function test_taking_the_task_of_another_agent_is_refused(): void
    {
        $t = $this->todo('For codex', ['agent' => 'codex']);

        $this->artisan('griglia:check', ['--take' => $t->id, '--agent' => 'claude'])
            ->expectsOutputToContain('belongs to agent «Codex CLI»')
            ->assertFailed();
        $this->assertFalse($t->fresh()->working, 'the task must stay untouched');

        $this->artisan('griglia:check', ['--take' => $t->id, '--agent' => 'codex'])
            ->expectsOutputToContain('taken in charge')
            ->assertSuccessful();
        $this->assertTrue($t->fresh()->working);
    }

    public function test_closing_or_asking_on_another_agent_task_is_refused(): void
    {
        $t = $this->todo('For codex', ['agent' => 'codex', 'working' => true]);

        $this->artisan('griglia:check', ['--done' => $t->id, '--agent' => 'claude'])
            ->expectsOutputToContain('being worked on right now')
            ->assertFailed();
        $this->artisan('griglia:check', ['--ask' => $t->id, '--q' => ['?'], '--agent' => 'claude'])
            ->expectsOutputToContain('refusing to ask questions on')
            ->assertFailed();

        $t->refresh();
        $this->assertFalse($t->completed);
        $this->assertFalse($t->question);
        $this->assertSame(0, $t->questions()->count());
    }

    public function test_force_takes_the_task_anyway(): void
    {
        $t = $this->todo('For codex', ['agent' => 'codex']);

        $this->artisan('griglia:check', ['--take' => $t->id, '--agent' => 'claude', '--force' => true])
            ->expectsOutputToContain('taken in charge')
            ->assertSuccessful();
        $this->assertTrue($t->fresh()->working);
    }

    public function test_the_new_marker_baseline_is_per_agent(): void
    {
        $mine = $this->todo('For claude');

        // the other agent checking the board must not consume my 🆕 markers
        $this->artisan('griglia:check', ['--agent' => 'codex'])->assertSuccessful();
        $this->artisan('griglia:check', ['--agent' => 'claude'])
            ->expectsOutputToContain('🆕')
            ->assertSuccessful();
        // …and once I have seen it, it is not new any more
        $this->artisan('griglia:check', ['--agent' => 'claude'])
            ->doesntExpectOutputToContain('🆕 [ ] 🟢 #'.$mine->order)
            ->assertSuccessful();
    }

    public function test_check_shows_what_the_other_agents_are_working_on(): void
    {
        $this->todo('Release the package', ['agent' => 'codex', 'working' => true, 'open_to_work' => false]);

        $this->artisan('griglia:check', ['--agent' => 'claude'])
            ->expectsOutputToContain('🔒 busy elsewhere: Codex CLI on «Release the package»')
            ->assertSuccessful();
        // the agent doing the work does not need the warning about itself
        $this->artisan('griglia:check', ['--agent' => 'codex'])
            ->doesntExpectOutputToContain('🔒 busy elsewhere')
            ->assertSuccessful();
    }

    /**
     * Task 652: a name is not a key. `--agent=Claude` (or «Claude Code», or CLAUDE) is the same agent as
     * `claude`, and the guard must not read it as somebody else — the report was «belongs to agent «Claude»,
     * you are «claude»: refusing to take it», two spellings of the same agent refusing each other.
     */
    public function test_the_agent_option_accepts_the_label_and_any_case(): void
    {
        $t = $this->todo('For claude', ['agent' => 'claude']);

        foreach (['Claude Code', 'CLAUDE', 'Claude', 'claude code'] as $spelling) {
            $this->artisan('griglia:check', ['--take' => $t->id, '--agent' => $spelling])
                ->expectsOutputToContain('taken in charge')
                ->assertSuccessful();
        }

        // …and it still is not a way into somebody else's task
        $other = $this->todo('For codex', ['agent' => 'codex']);
        $this->artisan('griglia:check', ['--take' => $other->id, '--agent' => 'Claude Code'])
            ->expectsOutputToContain('belongs to agent «Codex CLI»')
            ->assertFailed();
    }

    /** A label stored where a key belongs (older board, hand-edited row) still points at its agent. */
    public function test_a_label_stored_on_the_task_resolves_to_its_key(): void
    {
        $t = $this->todo('Mislabelled', ['agent' => 'Codex CLI']);

        $this->assertSame('codex', \Alle80\Griglia\Agent::effective($t));
        $this->artisan('griglia:check', ['--take' => $t->id, '--agent' => 'claude'])
            ->expectsOutputToContain('belongs to agent «Codex CLI»')
            ->assertFailed();
        $this->artisan('griglia:check', ['--take' => $t->id, '--agent' => 'codex'])
            ->expectsOutputToContain('taken in charge')
            ->assertSuccessful();
    }

    /** An agent key nobody configured must stop the command, not run as nobody and refuse every task. */
    public function test_an_unknown_agent_key_is_reported(): void
    {
        $t = $this->todo('For claude', ['agent' => 'claude']);

        $this->artisan('griglia:check', ['--take' => $t->id, '--agent' => 'gemini'])
            ->expectsOutputToContain('Unknown agent «gemini»: configured agents are claude (Claude Code), codex (Codex CLI)')
            ->assertFailed();
        $this->assertFalse($t->fresh()->working, 'the task must stay untouched');

        $this->artisan('griglia:watch', ['--agent' => 'gemini', '--once' => true])
            ->expectsOutputToContain('Unknown agent «gemini»')
            ->assertFailed();
    }

    /** One agent configured: `--agent=claude` from a worker is just decoration, never a refusal (task 652). */
    public function test_a_single_agent_board_ignores_the_agent_option(): void
    {
        config(['griglia.agents' => null, 'griglia.agent_key' => null, 'griglia.agent_name' => 'Claude']);
        $t = $this->todo('Sei pronto?');

        $this->artisan('griglia:check', ['--take' => $t->id, '--agent' => 'claude'])
            ->expectsOutputToContain('taken in charge')
            ->assertSuccessful();
        $this->assertTrue($t->fresh()->working);
    }
}
