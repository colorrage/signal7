<?php

namespace Tests\Unit;

use App\Contracts\CliCommandDispatcher;
use App\Models\Task;
use App\Services\PlaceholderCliCommandDispatcher;
use App\Services\QueuedCliCommandDispatcher;
use Tests\TestCase;

class PlaceholderCliCommandDispatcherTest extends TestCase
{
    public function test_container_now_resolves_real_dispatcher(): void
    {
        $dispatcher = $this->app->make(CliCommandDispatcher::class);

        $this->assertInstanceOf(QueuedCliCommandDispatcher::class, $dispatcher);
    }

    public function test_dispatch_throws_runtime_exception_with_command_in_message(): void
    {
        $dispatcher = new PlaceholderCliCommandDispatcher;
        $task = new Task(['id' => 42]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/approve/');

        $dispatcher->dispatch($task, 'approve');
    }

    public function test_dispatch_records_the_call_before_throwing(): void
    {
        $dispatcher = new PlaceholderCliCommandDispatcher;
        $task = new Task;
        $task->id = 99;

        try {
            $dispatcher->dispatch($task, 'claude /signal "approve"', ['--approver' => 'Owner']);
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertCount(1, $dispatcher->calls);
        $this->assertSame(99, $dispatcher->calls[0]['task_id']);
        $this->assertSame('claude /signal "approve"', $dispatcher->calls[0]['command']);
        $this->assertSame(['--approver' => 'Owner'], $dispatcher->calls[0]['args']);
    }

    public function test_dispatch_includes_command_in_exception_message(): void
    {
        $dispatcher = new PlaceholderCliCommandDispatcher;
        $task = new Task;
        $command = 'claude /signal "resolve compliance"';

        try {
            $dispatcher->dispatch($task, $command);
            $this->fail('Expected RuntimeException not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($command, $e->getMessage());
        }
    }

    public function test_taskless_dispatch_records_the_call_before_throwing(): void
    {
        $dispatcher = new PlaceholderCliCommandDispatcher;

        try {
            $dispatcher->dispatchTaskless('claude /signal-task list');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertCount(1, $dispatcher->calls);
        $this->assertNull($dispatcher->calls[0]['task_id']);
        $this->assertSame('claude /signal-task list', $dispatcher->calls[0]['command']);
    }
}
