<?php

namespace Tests\Feature\Scanner;

use App\Models\Subtask;
use App\Models\Task;
use App\Services\Scanner\SubtaskScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Requires Sail (MySQL) to run. Execute inside Docker:
 *   sail test --filter=SubtaskScannerTest
 */
class SubtaskScannerTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDir;

    private Task $task;

    private SubtaskScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/subtask-scanner-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->task = Task::create([
            'system' => 'hyper7',
            'task_id' => 'T99',
            'folder_name' => 'T99-test-task',
            'title' => 'Test Task',
            'phase' => 'implement',
            'scope' => 'feature',
        ]);

        $this->scanner = new SubtaskScanner;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    private function writeSubtask(string $filename, array $frontmatter, string $body = ''): void
    {
        $fm = "---\n";
        foreach ($frontmatter as $k => $v) {
            if (is_array($v)) {
                $encoded = '['.implode(', ', array_map(fn ($i) => '"'.$i.'"', $v)).']';
                $fm .= "{$k}: {$encoded}\n";
            } else {
                $fm .= "{$k}: ".($v === null ? 'null' : $v)."\n";
            }
        }
        $fm .= "---\n\n{$body}";
        file_put_contents($this->tempDir.'/'.$filename, $fm);
    }

    public function test_scans_single_subtask_and_upserts_row(): void
    {
        $this->writeSubtask('T99.1-first.md', [
            'id' => 'T99.1',
            'parent' => 'T99',
            'title' => 'First subtask',
            'status' => 'todo',
            'depends' => [],
            'writes' => ['app/Foo.php'],
            'role' => 'impl',
        ], '# First subtask body');

        $count = $this->scanner->scan($this->tempDir, $this->task);

        $this->assertEquals(1, $count);
        $subtask = Subtask::where('task_id', $this->task->id)
            ->where('subtask_id', 'T99.1')
            ->first();

        $this->assertNotNull($subtask);
        $this->assertEquals('First subtask', $subtask->title);
        $this->assertEquals('todo', $subtask->status);
        $this->assertNull($subtask->parent_subtask_id);
        $this->assertNull($subtask->depends);
        $this->assertEquals(['app/Foo.php'], $subtask->writes);
        $this->assertStringContainsString('First subtask body', $subtask->body);
    }

    public function test_scan_is_idempotent(): void
    {
        $this->writeSubtask('T99.1-first.md', [
            'id' => 'T99.1', 'parent' => 'T99', 'title' => 'A',
            'status' => 'todo', 'depends' => [], 'writes' => [], 'role' => 'impl',
        ]);

        $this->scanner->scan($this->tempDir, $this->task);
        $this->scanner->scan($this->tempDir, $this->task);

        $this->assertCount(1, Subtask::where('task_id', $this->task->id)->get());
    }

    public function test_scan_sets_depends_array(): void
    {
        $this->writeSubtask('T99.1-first.md', [
            'id' => 'T99.1', 'parent' => 'T99', 'title' => 'A',
            'status' => 'todo', 'depends' => [], 'writes' => [], 'role' => 'impl',
        ]);
        $this->writeSubtask('T99.2-second.md', [
            'id' => 'T99.2', 'parent' => 'T99', 'title' => 'B',
            'status' => 'todo', 'depends' => ['T99.1'], 'writes' => [], 'role' => 'impl',
        ]);

        $this->scanner->scan($this->tempDir, $this->task);

        $child = Subtask::where('subtask_id', 'T99.2')->first();
        $this->assertEquals(['T99.1'], $child->depends);
    }

    public function test_scan_removes_orphaned_rows(): void
    {
        $this->writeSubtask('T99.1-first.md', [
            'id' => 'T99.1', 'parent' => 'T99', 'title' => 'A',
            'status' => 'todo', 'depends' => [], 'writes' => [], 'role' => 'impl',
        ]);
        $this->writeSubtask('T99.2-second.md', [
            'id' => 'T99.2', 'parent' => 'T99', 'title' => 'B',
            'status' => 'todo', 'depends' => [], 'writes' => [], 'role' => 'impl',
        ]);

        $this->scanner->scan($this->tempDir, $this->task);
        $this->assertCount(2, Subtask::where('task_id', $this->task->id)->get());

        unlink($this->tempDir.'/T99.2-second.md');
        $this->scanner->scan($this->tempDir, $this->task);

        $this->assertCount(1, Subtask::where('task_id', $this->task->id)->get());
        $this->assertNull(Subtask::where('subtask_id', 'T99.2')->first());
    }

    public function test_scan_preserves_position_order(): void
    {
        $this->writeSubtask('T99.1-first.md', [
            'id' => 'T99.1', 'parent' => 'T99', 'title' => 'A',
            'status' => 'todo', 'depends' => [], 'writes' => [], 'role' => 'impl',
        ]);
        $this->writeSubtask('T99.2-second.md', [
            'id' => 'T99.2', 'parent' => 'T99', 'title' => 'B',
            'status' => 'todo', 'depends' => [], 'writes' => [], 'role' => 'impl',
        ]);

        $this->scanner->scan($this->tempDir, $this->task);

        $subtasks = Subtask::where('task_id', $this->task->id)->orderBy('position')->get();
        $this->assertEquals('T99.1', $subtasks->first()->subtask_id);
        $this->assertEquals('T99.2', $subtasks->last()->subtask_id);
    }

    public function test_empty_directory_scans_zero(): void
    {
        $count = $this->scanner->scan($this->tempDir, $this->task);
        $this->assertEquals(0, $count);
    }
}
