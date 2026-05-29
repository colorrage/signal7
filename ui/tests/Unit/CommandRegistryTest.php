<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Services\CommandRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CommandRegistryTest extends TestCase
{
    private function registry(): CommandRegistry
    {
        return new CommandRegistry([
            'signal.run' => [
                'label' => 'Continue Signal7 task',
                'description' => 'Run Signal7',
                'system' => 'signal7',
                'tokens' => ['claude', '/signal'],
                'requires_task' => false,
                'timeout' => 600,
                'arguments' => [
                    ['name' => 'task_id', 'label' => 'Task ID', 'type' => 'hidden', 'required' => false, 'default_from' => 'task.task_id', 'placement' => 'positional'],
                    ['name' => 'input', 'label' => 'Input', 'type' => 'textarea', 'required' => false, 'placement' => 'positional'],
                ],
            ],
            'signal.approve' => [
                'label' => 'Approve Signal7 gate',
                'description' => 'Approve',
                'system' => 'signal7',
                'tokens' => ['claude', '/signal'],
                'requires_task' => true,
                'timeout' => 600,
                'arguments' => [
                    ['name' => 'task_id', 'label' => 'Task ID', 'type' => 'hidden', 'required' => true, 'default_from' => 'task.task_id', 'placement' => 'positional'],
                    ['name' => 'response', 'label' => 'Response', 'type' => 'hidden', 'required' => true, 'default' => 'continue', 'placement' => 'positional'],
                ],
            ],
            'signal.publish' => [
                'label' => 'Publish Signal7 task',
                'description' => 'Publish',
                'system' => 'signal7',
                'tokens' => ['claude', '/signal'],
                'requires_task' => true,
                'timeout' => 600,
                'arguments' => [
                    ['name' => 'task_id', 'label' => 'Task ID', 'type' => 'hidden', 'required' => true, 'default_from' => 'task.task_id', 'placement' => 'positional'],
                    ['name' => 'input', 'label' => 'Input', 'type' => 'hidden', 'required' => true, 'default' => 'publish', 'placement' => 'positional'],
                    ['name' => 'assets', 'label' => 'Asset IDs', 'type' => 'hidden', 'required' => false, 'placement' => 'option', 'option' => '--assets'],
                ],
            ],
            'hyper-task.status' => [
                'label' => 'Hyper7 task status',
                'description' => 'Status',
                'system' => 'hyper7',
                'tokens' => ['claude', '/hyper-task', 'status'],
                'requires_task' => false,
                'timeout' => 120,
                'arguments' => [
                    ['name' => 'task_id', 'label' => 'Task ID', 'type' => 'text', 'required' => true, 'placement' => 'positional'],
                ],
            ],
        ]);
    }

    public function test_groups_commands_by_system(): void
    {
        $grouped = $this->registry()->grouped();

        $this->assertArrayHasKey('signal7', $grouped);
        $this->assertArrayHasKey('hyper7', $grouped);
        $this->assertArrayHasKey('signal.run', $grouped['signal7']);
        $this->assertArrayHasKey('hyper-task.status', $grouped['hyper7']);
    }

    public function test_returns_argument_schema(): void
    {
        $schema = $this->registry()->argumentSchema('hyper-task.status');

        $this->assertSame('task_id', $schema[0]['name']);
        $this->assertTrue($schema[0]['required']);
    }

    public function test_renders_preview_with_quoted_arguments(): void
    {
        $preview = $this->registry()->preview('signal.run', ['input' => 'approve this gate']);

        $this->assertSame("claude /signal 'approve this gate'", $preview);
    }

    public function test_builds_tokens_without_shell_string_concatenation(): void
    {
        $tokens = $this->registry()->tokens('hyper-task.status', ['task_id' => 'T12']);

        $this->assertSame(['claude', '/hyper-task', 'status', 'T12'], $tokens);
    }

    public function test_rejects_unknown_command_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown command key [missing.command].');

        $this->registry()->get('missing.command');
    }

    public function test_enforces_task_context_requirement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a task context');

        $this->registry()->tokens('signal.approve');
    }

    public function test_task_context_satisfies_task_required_command(): void
    {
        $task = new Task(['task_id' => 'S1']);
        $tokens = $this->registry()->tokens('signal.approve', [], $task);

        $this->assertSame(['claude', '/signal', 'S1', 'continue'], $tokens);
    }

    public function test_optional_task_context_is_rendered_when_available(): void
    {
        $task = new Task(['task_id' => 'S2']);
        $tokens = $this->registry()->tokens('signal.run', ['input' => 'yes'], $task);

        $this->assertSame(['claude', '/signal', 'S2', 'yes'], $tokens);
    }

    public function test_signal_publish_renders_optional_assets_filter(): void
    {
        $task = new Task(['task_id' => 'S3']);
        $tokens = $this->registry()->tokens('signal.publish', ['assets' => 'A1,A3'], $task);

        $this->assertSame(['claude', '/signal', 'S3', 'publish', '--assets', 'A1,A3'], $tokens);
    }
}
