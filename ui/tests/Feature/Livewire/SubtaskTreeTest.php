<?php

/**
 * NOTE: These tests require a running MySQL database (Sail).
 * They use RefreshDatabase and Livewire's test helpers.
 * Run inside Sail: ./vendor/bin/sail artisan test --filter=SubtaskTreeTest
 */

namespace Tests\Feature\Livewire;

use App\Livewire\SubtaskTree;
use App\Models\Subtask;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubtaskTreeTest extends TestCase
{
    use RefreshDatabase;

    private function makeHyper7Task(): Task
    {
        return Task::create([
            'system' => 'hyper7',
            'task_id' => 'T99',
            'folder_name' => 'T99-test-task',
            'title' => 'Test Task',
            'phase' => 'implement',
            'scope' => 'feature',
            'is_archived' => false,
            'awaiting' => null,
            'created_at_disk' => now(),
            'dashboard_summary' => null,
        ]);
    }

    public function test_renders_subtask_ids_and_status_badges(): void
    {
        $task = $this->makeHyper7Task();

        // Parent subtask
        $parent = Subtask::create([
            'task_id' => $task->id,
            'subtask_id' => 'T99.1',
            'parent_subtask_id' => null,
            'title' => 'Parent Subtask',
            'status' => 'done',
            'awaiting' => null,
            'role' => 'impl',
            'depends' => [],
            'writes' => ['app/Models/Foo.php'],
            'body' => '# Parent\n\nParent body.',
            'position' => 1,
        ]);

        // Child 1
        Subtask::create([
            'task_id' => $task->id,
            'subtask_id' => 'T99.2',
            'parent_subtask_id' => 'T99.1',
            'title' => 'Child One',
            'status' => 'done',
            'awaiting' => null,
            'role' => 'impl',
            'depends' => [],
            'writes' => [],
            'body' => 'Child one body.',
            'position' => 2,
        ]);

        // Child 2 depends on child 1
        Subtask::create([
            'task_id' => $task->id,
            'subtask_id' => 'T99.3',
            'parent_subtask_id' => 'T99.1',
            'title' => 'Child Two',
            'status' => 'in-progress',
            'awaiting' => null,
            'role' => 'impl',
            'depends' => ['T99.2'],
            'writes' => ['app/Services/Bar.php'],
            'body' => 'Child two body.',
            'position' => 3,
        ]);

        $component = Livewire::test(SubtaskTree::class, ['taskId' => $task->id]);

        $component
            ->assertSee('T99.1')
            ->assertSee('T99.2')
            ->assertSee('T99.3')
            ->assertSee('done')
            ->assertSee('in-progress')
            ->assertSee('Parent Subtask')
            ->assertSee('Child One')
            ->assertSee('Child Two');
    }

    public function test_open_shows_reader_with_body_and_writes_and_depends(): void
    {
        $task = $this->makeHyper7Task();

        $parent = Subtask::create([
            'task_id' => $task->id,
            'subtask_id' => 'T99.1',
            'parent_subtask_id' => null,
            'title' => 'Parent Subtask',
            'status' => 'done',
            'awaiting' => null,
            'role' => 'impl',
            'depends' => [],
            'writes' => ['app/Models/Foo.php'],
            'body' => 'Parent body content.',
            'position' => 1,
        ]);

        $child = Subtask::create([
            'task_id' => $task->id,
            'subtask_id' => 'T99.2',
            'parent_subtask_id' => 'T99.1',
            'title' => 'Child Subtask',
            'status' => 'in-progress',
            'awaiting' => null,
            'role' => 'impl',
            'depends' => ['T99.1'],
            'writes' => ['app/Services/Bar.php'],
            'body' => 'Child detail body.',
            'position' => 2,
        ]);

        Livewire::test(SubtaskTree::class, ['taskId' => $task->id])
            ->call('open', $child->id)
            ->assertSee('Child Subtask')
            ->assertSee('Child detail body.')
            ->assertSee('app/Services/Bar.php')
            ->assertSee('T99.1');
    }

    public function test_close_hides_modal(): void
    {
        $task = $this->makeHyper7Task();

        $subtask = Subtask::create([
            'task_id' => $task->id,
            'subtask_id' => 'T99.1',
            'parent_subtask_id' => null,
            'title' => 'A Subtask',
            'status' => 'todo',
            'awaiting' => null,
            'role' => 'impl',
            'depends' => [],
            'writes' => [],
            'body' => 'Some body.',
            'position' => 1,
        ]);

        Livewire::test(SubtaskTree::class, ['taskId' => $task->id])
            ->call('open', $subtask->id)
            ->assertSet('selectedId', $subtask->id);
    }

    public function test_empty_state_renders_for_task_with_no_subtasks(): void
    {
        $task = $this->makeHyper7Task();

        Livewire::test(SubtaskTree::class, ['taskId' => $task->id])
            ->assertSee('No subtasks yet.');
    }

    public function test_dependency_pills_show_correct_status(): void
    {
        $task = $this->makeHyper7Task();

        Subtask::create([
            'task_id' => $task->id,
            'subtask_id' => 'T99.1',
            'parent_subtask_id' => null,
            'title' => 'First',
            'status' => 'done',
            'awaiting' => null,
            'role' => 'impl',
            'depends' => [],
            'writes' => [],
            'body' => null,
            'position' => 1,
        ]);

        Subtask::create([
            'task_id' => $task->id,
            'subtask_id' => 'T99.2',
            'parent_subtask_id' => null,
            'title' => 'Second',
            'status' => 'todo',
            'awaiting' => null,
            'role' => 'impl',
            'depends' => ['T99.1'],
            'writes' => [],
            'body' => null,
            'position' => 2,
        ]);

        // The dependency pill for T99.1 from T99.2's perspective should show 'done' (✓)
        Livewire::test(SubtaskTree::class, ['taskId' => $task->id])
            ->assertSee('T99.1')
            ->assertSee('T99.2');
    }
}
