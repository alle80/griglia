<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Agent;
use Alle80\Griglia\Livewire\IngredientModal;
use Alle80\Griglia\Livewire\TodoList;
use Alle80\Griglia\Models\Checklist;
use Alle80\Griglia\Models\Todo;
use Alle80\Griglia\Tests\TestCase;
use Livewire\Livewire;

/**
 * Model and reasoning effort of the agent session (task 641): catalogue from config, list default, task
 * override, what `griglia:check` prints and what the persistent worker receives.
 */
class ModelAndEffortTest extends TestCase
{
    private function board(): array
    {
        config([
            'griglia.agents' => 'claude:Claude Code,codex:Codex CLI',
            'griglia.agent_models' => 'claude:opus=Opus,sonnet;codex:gpt-5',
            'griglia.agent_efforts' => 'low,medium,high',
        ]);
        $user = $this->actingAsUser();
        $list = Checklist::create(['name' => 'dev', 'user_id' => $user->id]);
        session(['checklist_id' => $list->id]);
        $todo = Todo::create(['title' => 'Ship it', 'order' => 1, 'checklist_id' => $list->id, 'open_to_work' => true]);

        return [$list, $todo];
    }

    public function test_no_catalogue_by_default(): void
    {
        $this->assertSame([], Agent::models());
        $this->assertSame([], Agent::efforts());
    }

    public function test_catalogue_is_read_per_agent(): void
    {
        $this->board();

        // «claude:opus=Opus,sonnet» — one group per agent, «value=Label» renames the option
        $this->assertSame(['opus' => 'Opus', 'sonnet' => 'sonnet'], Agent::models('claude'));
        $this->assertSame(['gpt-5' => 'gpt-5'], Agent::models('codex'));
        $this->assertSame([], Agent::models('gemini'), 'an agent without a group has no picker');
        // a bare list is shared by every agent
        $this->assertSame(['low' => 'low', 'medium' => 'medium', 'high' => 'high'], Agent::efforts('codex'));
        // labels resolve like everywhere else (task 652)
        $this->assertSame(Agent::models('claude'), Agent::models('Claude Code'));

        // array form, per agent and flat
        config(['griglia.agent_models' => ['claude' => ['opus'], 'codex' => ['gpt-5']]]);
        $this->assertSame(['opus' => 'opus'], Agent::models('claude'));
        config(['griglia.agent_models' => ['opus', 'sonnet']]);
        $this->assertSame(['opus' => 'opus', 'sonnet' => 'sonnet'], Agent::models('codex'));
    }

    public function test_task_inherits_the_list_and_overrides_it(): void
    {
        [$list, $todo] = $this->board();

        $this->assertNull(Agent::effectiveModel($todo), 'nothing chosen = the CLI keeps its own default');

        Livewire::test(TodoList::class)->call('setListModel', 'sonnet')->assertDispatched('toast');
        Livewire::test(TodoList::class)->call('setListEffort', 'medium')->assertDispatched('toast');
        $this->assertSame('sonnet', $list->fresh()->model);
        $this->assertSame('sonnet', Agent::effectiveModel($todo->fresh()));
        $this->assertSame('medium', Agent::effectiveEffort($todo->fresh()));

        Livewire::test(TodoList::class)->call('setTodoModel', $todo->id, 'opus')->assertDispatched('toast');
        Livewire::test(TodoList::class)->call('setTodoEffort', $todo->id, 'high')->assertDispatched('toast');
        $this->assertSame('opus', Agent::effectiveModel($todo->fresh()));
        $this->assertSame('high', Agent::effectiveEffort($todo->fresh()));

        // empty = back to the list's value
        Livewire::test(TodoList::class)->call('setTodoModel', $todo->id, '');
        $this->assertNull($todo->fresh()->model);
        $this->assertSame('sonnet', Agent::effectiveModel($todo->fresh()));
    }

    public function test_only_values_the_agent_offers_are_accepted(): void
    {
        [, $todo] = $this->board();

        Livewire::test(TodoList::class)->call('setTodoModel', $todo->id, 'gpt-5');
        $this->assertNull($todo->fresh()->model, 'a model of another agent is refused');
        Livewire::test(TodoList::class)->call('setTodoEffort', $todo->id, 'insane');
        $this->assertNull($todo->fresh()->effort);

        // a model the agent does not offer any more (task reassigned, catalogue changed) is ignored, not passed on
        $todo->update(['model' => 'opus', 'agent' => 'codex']);
        $this->assertNull(Agent::effectiveModel($todo->fresh()));

        // while the agent works the value is frozen: its session already started with the old one
        $todo->update(['agent' => 'claude', 'model' => null, 'working' => true]);
        Livewire::test(TodoList::class)->call('setTodoModel', $todo->id, 'opus');
        $this->assertNull($todo->fresh()->model);
    }

    public function test_row_and_modal_show_the_selects(): void
    {
        [, $todo] = $this->board();

        Livewire::test(TodoList::class)
            ->assertSeeHtml('setTodoModel('.$todo->id.', $event.target.value)')
            ->assertSeeHtml('setTodoEffort('.$todo->id.', $event.target.value)')
            ->assertSeeHtml('db-preset-model')
            ->assertSeeHtml('setListModel($event.target.value)');

        Livewire::test(IngredientModal::class)->call('openFor', $todo->id)
            ->assertSeeHtml('setModel($event.target.value)')
            ->assertSeeHtml('setEffort($event.target.value)');

        Livewire::test(IngredientModal::class)->call('openFor', $todo->id)->call('setModel', 'opus');
        $this->assertSame('opus', $todo->fresh()->model);
        Livewire::test(IngredientModal::class)->call('openFor', $todo->id)->call('setEffort', 'nope');
        $this->assertNull($todo->fresh()->effort);
        Livewire::test(IngredientModal::class)->call('openFor', $todo->id)->call('setEffort', 'high');
        $this->assertSame('high', $todo->fresh()->effort);
    }

    public function test_check_prints_them_and_the_worker_json_resolves_them(): void
    {
        [$list, $todo] = $this->board();
        $list->update(['model' => 'sonnet']);
        $todo->update(['effort' => 'high']);

        $this->artisan('griglia:check')
            ->expectsOutputToContain('{agent: claude, model: sonnet, effort: high}')
            ->assertSuccessful();

        $this->artisan('griglia:check', ['--worker-json' => true])->assertSuccessful();
        $json = json_decode($this->workerJson(), true);
        $item = collect($json['items'])->firstWhere('id', $todo->id);
        $this->assertSame('sonnet', $item['effective_model']);
        $this->assertSame('high', $item['effective_effort']);
        $this->assertSame('claude', $item['effective_agent']);
        $this->assertNull($item['model'], 'the raw column stays the task\'s own value');
    }

    /**
     * The default the agent CLI starts with, declared in config, is NAMED in brackets wherever nothing is
     * chosen — list toolbar, task badge, modal — instead of a bare «CLI default» (task 659). The board never
     * sends it: `effective_model` stays empty, so each worker keeps its own `GRIGLIA_WORKER_MODEL`.
     */
    public function test_the_declared_cli_default_is_named_but_never_sent(): void
    {
        [, $todo] = $this->board();
        config([
            'griglia.agent_default_model' => 'claude:sonnet;codex:gpt-5',
            'griglia.agent_default_effort' => 'medium',
        ]);

        $this->assertSame('sonnet', Agent::defaultModel('claude'));
        $this->assertSame('gpt-5', Agent::defaultModel('codex'));
        $this->assertSame('medium', Agent::defaultEffort('claude'));
        $this->assertNull(Agent::effectiveModel($todo), 'a declared default is not a choice of the board');

        // Not offered any more (catalogue changed, task reassigned) = nothing to name
        config(['griglia.agent_default_model' => 'claude:o3']);
        $this->assertNull(Agent::defaultModel('claude'));
        config(['griglia.agent_default_model' => 'claude:sonnet;codex:gpt-5']);

        Livewire::test(TodoList::class)
            ->assertSeeHtml('(sonnet)')          // task badge: the default it will run on, in brackets
            ->assertSeeHtml('Default (sonnet)')  // list toolbar: «nothing chosen» names it
            ->assertSeeHtml('Default (medium)')
            ->assertDontSeeHtml('CLI default');

        Livewire::test(IngredientModal::class)->call('openFor', $todo->id)
            ->assertSeeHtml('Default (sonnet)')
            ->assertSeeHtml('Default (medium)');

        // …and the agent reads it in the same shape
        $this->artisan('griglia:check')
            ->expectsOutputToContain('{agent: claude, model: (sonnet), effort: (medium)}')
            ->assertSuccessful();

        $this->artisan('griglia:check', ['--worker-json' => true])->assertSuccessful();
        $item = collect(json_decode($this->workerJson(), true)['items'])->firstWhere('id', $todo->id);
        $this->assertNull($item['effective_model'], 'the worker keeps its own default');
        $this->assertNull($item['effective_effort']);
    }

    /** Every picker says what it is: agent, model, effort (task 659). */
    public function test_the_three_pickers_carry_their_label(): void
    {
        [, $todo] = $this->board();

        Livewire::test(TodoList::class)
            ->assertSeeHtml('>Agent</span>')   // task row, next to its chip
            ->assertSeeHtml('>Model</span>')
            ->assertSeeHtml('>Effort</span>')
            ->assertSeeHtml('>Agent</label>')  // list toolbar
            ->assertSeeHtml('>Model</label>')
            ->assertSeeHtml('>Effort</label>');

        Livewire::test(IngredientModal::class)->call('openFor', $todo->id)
            ->assertSeeHtml('>Agent</span>')
            ->assertSeeHtml('>Model</span>')
            ->assertSeeHtml('>Effort</span>');
    }

    /** The board output of `griglia:check --worker-json`, captured as the worker reads it. */
    private function workerJson(): string
    {
        $command = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $command->call('griglia:check', ['--worker-json' => true], $output);

        return $output->fetch();
    }

    /** The worker gives the task's own model and effort to the CLI, its own only as a fallback. */
    public function test_worker_script_prefers_the_board_values(): void
    {
        $worker = file_get_contents(__DIR__.'/../../scripts/griglia-agent-worker.py');
        $this->assertStringContainsString('task.get("effective_model") or args.model', $worker);
        $this->assertStringContainsString('task.get("effective_effort") or args.effort', $worker);
        $this->assertStringContainsString('driver_command(args, prompt(args.agent, task), model, effort)', $worker);
    }
}
