<?php

/**
 * Smoke tests for Signal7 and Hyper7 ViewTask Filament pages.
 *
 * NOTE: These tests require a running MySQL database (Sail).
 * Run inside Sail: ./vendor/bin/sail artisan test --filter=ViewTask
 *
 * Uses Livewire::actingAs() to bypass Filament panel auth middleware,
 * matching the pattern established in Signal7ViewTaskGateTest.
 *
 * Asserts that:
 * - The Signal7 ViewTask page renders without error for an authenticated user.
 * - The Hyper7 ViewTask page renders without error for an authenticated user.
 * - The pipeline-root mount div is present in both responses.
 */

namespace Tests\Feature;

use App\Filament\Clusters\Hyper7\Resources\TaskResource\Pages\ViewTask as Hyper7ViewTask;
use App\Filament\Clusters\Signal7\Resources\TaskResource\Pages\ViewTask as Signal7ViewTask;
use App\Models\Asset;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ViewTaskSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(string $system, string $scope = 'quick'): Task
    {
        return Task::create([
            'system' => $system,
            'task_id' => $system === 'signal7' ? 'S99' : 'T99',
            'folder_name' => "{$system}-99-smoke-test",
            'title' => 'Smoke Test Task',
            'phase' => 'create',
            'scope' => $scope,
            'is_archived' => false,
            'awaiting' => null,
            'created_at_disk' => now(),
            'dashboard_summary' => null,
        ]);
    }

    private function configureDiskRoot(Task $task, string $filename, string $content): void
    {
        $root = sys_get_temp_dir().'/view-task-docs-'.bin2hex(random_bytes(6));
        $base = $root.($task->system === 'signal7' ? '/.signal' : '/.hyper');
        $taskDir = $base.'/tasks/'.$task->folder_name;
        mkdir($taskDir, 0o755, true);
        file_put_contents($taskDir.'/'.$filename, $content);

        config([
            $task->system === 'signal7' ? 'disksync.signal' : 'disksync.hyper' => $base,
        ]);
    }

    public function test_signal7_view_task_renders(): void
    {
        $user = User::factory()->create();
        $task = $this->makeTask('signal7', 'quick');

        Livewire::actingAs($user)
            ->test(Signal7ViewTask::class, ['record' => $task->getKey()])
            ->assertSuccessful();
    }

    public function test_signal7_view_task_renders_pipeline_root(): void
    {
        $user = User::factory()->create();
        $task = $this->makeTask('signal7', 'quick');

        Livewire::actingAs($user)
            ->test(Signal7ViewTask::class, ['record' => $task->getKey()])
            ->assertSee('pipeline-root');
    }

    public function test_signal7_view_task_shows_revision_history_tab_when_assets_exist(): void
    {
        $user = User::factory()->create();
        $task = $this->makeTask('signal7', 'campaign');
        Asset::create([
            'task_id' => $task->id,
            'asset_id' => 'A1',
            'title' => 'Launch Post',
            'asset_type' => 'social-copy',
            'status' => 'done',
        ]);

        Livewire::actingAs($user)
            ->test(Signal7ViewTask::class, ['record' => $task->getKey()])
            ->assertSee('Revision History');
    }

    public function test_hyper7_view_task_renders(): void
    {
        $user = User::factory()->create();
        $task = $this->makeTask('hyper7', 'feature');

        Livewire::actingAs($user)
            ->test(Hyper7ViewTask::class, ['record' => $task->getKey()])
            ->assertSuccessful();
    }

    public function test_hyper7_view_task_renders_pipeline_root(): void
    {
        $user = User::factory()->create();
        $task = $this->makeTask('hyper7', 'feature');

        Livewire::actingAs($user)
            ->test(Hyper7ViewTask::class, ['record' => $task->getKey()])
            ->assertSee('pipeline-root');
    }

    public function test_hyper7_view_task_shows_documents_and_subtasks_tabs(): void
    {
        $user = User::factory()->create();
        $task = $this->makeTask('hyper7', 'feature');
        $this->configureDiskRoot($task, '03-technical-plan.md', '# Technical Plan');
        Subtask::create([
            'task_id' => $task->id,
            'subtask_id' => 'T99.1',
            'parent_subtask_id' => null,
            'title' => 'Visible Subtask',
            'status' => 'todo',
            'position' => 1,
        ]);

        Livewire::actingAs($user)
            ->test(Hyper7ViewTask::class, ['record' => $task->getKey()])
            ->assertSee('Documents')
            ->assertSee('Subtasks')
            ->set('activeTaskTab', 'documents')
            ->assertSee('Technical Plan')
            ->set('activeTaskTab', 'subtasks')
            ->assertSee('Visible Subtask');
    }

    public function test_signal7_view_task_shows_documents_tab(): void
    {
        $user = User::factory()->create();
        $task = $this->makeTask('signal7', 'strategy');
        $this->configureDiskRoot($task, 'brief.md', '# Brief');

        Livewire::actingAs($user)
            ->test(Signal7ViewTask::class, ['record' => $task->getKey()])
            ->assertSee('Documents')
            ->set('activeTaskTab', 'documents')
            ->assertSee('Brief');
    }
}
