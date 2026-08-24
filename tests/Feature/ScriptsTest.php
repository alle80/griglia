<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\GrigliaServiceProvider;
use Alle80\Griglia\Tests\TestCase;
use Illuminate\Support\ServiceProvider;

/**
 * The host-side helpers (scripts/) ship with the package: they run on the machine where the agent runs
 * — outside the container — and fill the board (skills, context, tokens, agent status). Task 376.
 */
class ScriptsTest extends TestCase
{
    public function test_scripts_are_shipped_and_publishable(): void
    {
        $dir = dirname(__DIR__, 2).'/scripts';
        foreach (['sync-skills.py', 'sync-context.py', 'claude-tokens.py', 'agent-status.py', 'builtin-skills.json', 'griglia-agent-worker.py', 'systemd/griglia-agent-worker@.service.example'] as $file) {
            $this->assertFileExists($dir.'/'.$file);
        }
        foreach (['sync-skills.py', 'sync-context.py', 'claude-tokens.py', 'agent-status.py', 'griglia-agent-worker.py'] as $script) {
            $this->assertTrue(is_executable($dir.'/'.$script), "$script must be executable");
            $this->assertStringStartsWith('#!/usr/bin/env python3', (string) file_get_contents($dir.'/'.$script));
        }

        // The ones reading/writing project files must find its root even when run from vendor/alle80/griglia/scripts
        foreach (['sync-skills.py', 'sync-context.py', 'claude-tokens.py'] as $script) {
            $this->assertStringContainsString('def project_root()', (string) file_get_contents($dir.'/'.$script));
        }

        // No Docker required: every host script can reach Artisan through local PHP (task 389) and picks the
        // transport by itself when GRIGLIA_TRANSPORT does not name one (task 645)
        foreach (['sync-skills.py', 'sync-context.py', 'claude-tokens.py', 'agent-status.py'] as $script) {
            $source = (string) file_get_contents($dir.'/'.$script);
            $this->assertStringContainsString("os.environ.get('GRIGLIA_TRANSPORT', 'auto')", $source, "$script must default to the automatic transport");
            $this->assertStringContainsString('def container_running():', $source, "$script must probe the container before using Docker");
            $this->assertStringContainsString('def transport():', $source, "$script must resolve the transport in one place");
            $this->assertStringContainsString('def transport_hint():', $source, "$script must tell which transport failed");
            $this->assertStringContainsString("os.environ.get('GRIGLIA_PHP', 'php')", $source, "$script must honour GRIGLIA_PHP");
            $this->assertStringNotContainsString("'laravel-dev-app'", str_replace("os.environ.get('GRIGLIA_CONTAINER', 'laravel-dev-app')", '', $source), "$script must not hardcode the container name");
        }

        $worker = (string) file_get_contents($dir.'/griglia-agent-worker.py');
        $this->assertStringContainsString('choices=("codex", "claude", "custom")', $worker);
        $this->assertStringContainsString('choices=("auto", "docker", "local")', $worker, 'the worker must accept the automatic transport');
        $this->assertStringContainsString('resolve_transport(args)', $worker, 'the automatic transport must be resolved before the first board call');
        $this->assertStringContainsString('GRIGLIA_WORKER_TRANSPORT', $worker);
        $this->assertStringContainsString('GRIGLIA_WORKER_PHP', $worker);
        $this->assertStringContainsString('["docker", "exec", args.container, "php", *artisan]', $worker);
        $this->assertStringContainsString('[args.php, *artisan]', $worker);
        $this->assertStringContainsString('hashlib.sha256(str(repo).encode()).hexdigest()[:12]', $worker);
        $this->assertStringContainsString('lock_path(args.repo, args.agent)', $worker);
        $this->assertStringContainsString('GRIGLIA_WORKER_MAX_PARALLEL', $worker);
        $this->assertStringContainsString('state.get("task_mode") == "multitasking"', $worker);
        $this->assertStringContainsString('running: dict[int, Session]', $worker);
        $this->assertStringContainsString('item.get("working") or item.get("open_to_work") or item.get("paused")', $worker, 'a paused task must be automatically eligible when the worker has a free slot');
        $this->assertStringContainsString('provider_limit(state, args.agent)', $worker);
        $this->assertStringContainsString('pause(args, int(task["id"]), phase)', $worker);
        $this->assertStringContainsString('eligible = []', $worker, 'a limited provider must not dispatch sessions');
        // Task 507: the board JSON is read even when a warning follows it; the script reloads itself in place
        // (same PID, sessions handed over) when it changes on disk; SIGHUP drains it instead of killing sessions
        $this->assertStringContainsString('json.JSONDecoder().raw_decode(text, start)', $worker);
        $this->assertStringContainsString('if not claim(args, task):', $worker, 'an agent must claim through the current board guard before its CLI starts');
        $this->assertStringContainsString('command.append(f"--take={task[\'id\']}")', $worker, 'the claim must be atomic with the board ownership check');
        $this->assertStringContainsString('os.execv(sys.executable, [sys.executable, str(source), *argv])', $worker);
        $this->assertStringContainsString('"--adopt="', $worker);
        $this->assertStringContainsString('f"--lock-fd={lock.fileno()}"', $worker);
        $this->assertStringContainsString('signal.signal(signal.SIGHUP, self.handle)', $worker);
        // The model and the reasoning effort of the dispatched session are configurable (task 475)
        foreach (['GRIGLIA_WORKER_MODEL', 'GRIGLIA_WORKER_EFFORT'] as $variable) {
            $this->assertStringContainsString('os.getenv("'.$variable.'")', $worker, "the worker must read $variable");
        }
        $this->assertStringContainsString('command += ["--model", model]', $worker);
        $this->assertStringContainsString('command += ["--effort", effort]', $worker);
        // …and a task that picked its own on the board wins over the worker's default (task 641)
        $this->assertStringContainsString('task.get("effective_model") or args.model', $worker);
        $this->assertStringContainsString('model_reasoning_effort=', $worker);
        // Per-instance variables win, but the shared ones configure the whole machine at once
        foreach (['GRIGLIA_TRANSPORT', 'GRIGLIA_PHP', 'GRIGLIA_CONTAINER'] as $shared) {
            $this->assertStringContainsString('os.getenv("'.$shared.'"', $worker, "the worker must fall back to $shared");
        }
        $unit = (string) file_get_contents($dir.'/systemd/griglia-agent-worker@.service.example');
        $this->assertStringContainsString('%h/.local/bin', $unit);
        $this->assertStringContainsString('/absolute/path/to/project', $unit);
        $this->assertStringContainsString('%p-%i.env', $unit);

        $paths = ServiceProvider::pathsToPublish(GrigliaServiceProvider::class, 'griglia-scripts');
        $this->assertCount(1, $paths);
        $this->assertSame(realpath($dir), realpath((string) array_key_first($paths)));
        $this->assertSame(base_path('scripts'), reset($paths));
    }

    public function test_python_scripts_have_valid_syntax(): void
    {
        // A script that does not parse never reaches the host: the worker would stay down until someone looks at
        // the journal (task 507). Skipped where the test runner has no python3.
        $python = trim((string) shell_exec('command -v python3 2>/dev/null'));
        if ($python === '') {
            $this->markTestSkipped('python3 is not available on this runner');
        }
        $dir = dirname(__DIR__, 2).'/scripts';
        foreach (['sync-skills.py', 'sync-context.py', 'claude-tokens.py', 'agent-status.py', 'griglia-agent-worker.py'] as $script) {
            $output = [];
            exec(escapeshellarg($python).' -c '.escapeshellarg('import ast, sys; ast.parse(open(sys.argv[1]).read(), sys.argv[1])').' '.escapeshellarg($dir.'/'.$script).' 2>&1', $output, $code);
            $this->assertSame(0, $code, "$script does not parse: ".implode("\n", $output));
        }
    }

    public function test_transport_is_docker_or_local_depending_on_the_machine(): void
    {
        // Task 645: on a machine without Docker — Laravel served by `composer dev`, Apache or nginx — the
        // scripts must reach Artisan anyway, without the user knowing that GRIGLIA_TRANSPORT exists.
        $python = trim((string) shell_exec('command -v python3 2>/dev/null'));
        if ($python === '') {
            $this->markTestSkipped('python3 is not available on this runner');
        }
        $dir = dirname(__DIR__, 2).'/scripts';
        $root = $this->tempProject();
        $withDocker = $root.'/fake-bin';   // a `docker` reporting the container up

        foreach (['sync-skills.py', 'sync-context.py', 'claude-tokens.py', 'agent-status.py'] as $script) {
            $this->assertSame('local', $this->transportOf($python, $dir.'/'.$script, $root, ''), "$script must fall back to local PHP where Docker cannot answer");
            $this->assertSame('docker', $this->transportOf($python, $dir.'/'.$script, $root, $withDocker), "$script must use the container when it is running");
            $this->assertSame('docker', $this->transportOf($python, $dir.'/'.$script, $root, '', 'docker'), "$script must obey an explicit GRIGLIA_TRANSPORT");
            $this->assertSame('local', $this->transportOf($python, $dir.'/'.$script, $root, $withDocker, 'local'), "$script must obey an explicit GRIGLIA_TRANSPORT");
        }

        // The worker resolves the same way, through its own --transport auto
        $code = 'import importlib.util, pathlib, sys, types;'
            .'spec = importlib.util.spec_from_file_location("worker", sys.argv[1]);'
            .'m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m);'
            .'print(m.resolve_transport(types.SimpleNamespace(transport="auto", container="griglia-test", repo=pathlib.Path(sys.argv[2]))))';
        $this->assertSame('local', $this->runPython($python, $code, [$dir.'/griglia-agent-worker.py', $root], ''));
        $this->assertSame('docker', $this->runPython($python, $code, [$dir.'/griglia-agent-worker.py', $root], $withDocker));
    }

    /** Resolved transport of a host script, with `artisan` in $root and $path prepended to PATH. */
    private function transportOf(string $python, string $script, string $root, string $path, ?string $forced = null): string
    {
        $code = 'import importlib.util, sys;'
            .'spec = importlib.util.spec_from_file_location("script", sys.argv[1]);'
            .'m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m);'
            .'print(m.transport())';

        return $this->runPython($python, $code, [$script], $path, [
            'GRIGLIA_PROJECT_ROOT' => $root,
            'GRIGLIA_CONTAINER' => 'griglia-test',
        ] + ($forced === null ? [] : ['GRIGLIA_TRANSPORT' => $forced]));
    }

    private function runPython(string $python, string $code, array $arguments, string $path, array $environment = []): string
    {
        $command = '';
        foreach ($environment + ['GRIGLIA_CONTAINER' => 'griglia-test', 'PATH' => $path] as $name => $value) {
            $command .= $name.'='.escapeshellarg($value).' ';
        }
        $command .= escapeshellarg($python).' -c '.escapeshellarg($code);
        foreach ($arguments as $argument) {
            $command .= ' '.escapeshellarg($argument);
        }
        $output = [];
        exec($command.' 2>&1', $output, $status);
        $this->assertSame(0, $status, 'python failed: '.implode("\n", $output));

        return trim((string) end($output));
    }

    /** A project root the scripts can accept (it holds `artisan`) plus a fake `docker` saying the container is up. */
    private function tempProject(): string
    {
        $root = sys_get_temp_dir().'/griglia-transport-'.getmypid();
        @mkdir($root.'/fake-bin', 0777, true);
        file_put_contents($root.'/artisan', "#!/usr/bin/env php\n");
        file_put_contents($root.'/fake-bin/docker', "#!/bin/sh\necho true\n");
        chmod($root.'/fake-bin/docker', 0755);

        return $root;
    }
}
