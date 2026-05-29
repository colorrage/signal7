<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Services\TaskDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/task-documents-'.bin2hex(random_bytes(6));
        mkdir($this->root.'/.hyper/tasks/T99-docs', 0o755, true);
        mkdir($this->root.'/.signal/tasks/S99-docs', 0o755, true);

        config([
            'disksync.hyper' => $this->root.'/.hyper',
            'disksync.signal' => $this->root.'/.signal',
        ]);
    }

    protected function tearDown(): void
    {
        $this->rmTree($this->root);
        parent::tearDown();
    }

    public function test_discovers_hyper_documents_and_subtasks(): void
    {
        file_put_contents($this->root.'/.hyper/tasks/T99-docs/03-technical-plan.md', '# Technical Plan');
        file_put_contents($this->root.'/.hyper/tasks/T99-docs/04-execution-plan.md', '# Execution Plan');
        file_put_contents($this->root.'/.hyper/tasks/T99-docs/research.md', '# Research');
        file_put_contents($this->root.'/.hyper/tasks/T99-docs/checks.md', '# Checks');
        file_put_contents($this->root.'/.hyper/tasks/T99-docs/docs.md', '# Docs');
        file_put_contents($this->root.'/.hyper/tasks/T99-docs/handoff.md', '# Handoff');
        file_put_contents($this->root.'/.hyper/tasks/T99-docs/T99.1-first-slice.md', '# First Slice');

        $task = Task::create([
            'system' => 'hyper7',
            'task_id' => 'T99',
            'folder_name' => 'T99-docs',
            'title' => 'Docs',
            'phase' => 'implement',
            'scope' => 'feature',
            'is_archived' => false,
        ]);

        $documents = (new TaskDocumentService)->documentsFor($task);

        $this->assertSame([
            '03-technical-plan.md',
            '04-execution-plan.md',
            'research.md',
            'checks.md',
            'docs.md',
            'handoff.md',
            'T99.1-first-slice.md',
        ], array_column($documents, 'filename'));
    }

    public function test_discovers_signal_documents_and_asset_markdown(): void
    {
        file_put_contents($this->root.'/.signal/tasks/S99-docs/brief.md', '# Brief');
        file_put_contents($this->root.'/.signal/tasks/S99-docs/content-plan.md', '# Content Plan');
        file_put_contents($this->root.'/.signal/tasks/S99-docs/review.md', '# Review');
        file_put_contents($this->root.'/.signal/tasks/S99-docs/compliance.md', '# Compliance');
        file_put_contents($this->root.'/.signal/tasks/S99-docs/publish-log.md', '# Publish Log');
        file_put_contents($this->root.'/.signal/tasks/S99-docs/A1-research.md', '# Research Asset');

        $task = Task::create([
            'system' => 'signal7',
            'task_id' => 'S99',
            'folder_name' => 'S99-docs',
            'title' => 'Docs',
            'phase' => 'done',
            'scope' => 'strategy',
            'is_archived' => false,
        ]);

        $documents = (new TaskDocumentService)->documentsFor($task);

        $this->assertSame([
            'brief.md',
            'content-plan.md',
            'review.md',
            'compliance.md',
            'publish-log.md',
            'A1-research.md',
        ], array_column($documents, 'filename'));
    }

    public function test_renders_github_markdown_and_strips_frontmatter_from_reader_body(): void
    {
        file_put_contents($this->root.'/.signal/tasks/S99-docs/brief.md', <<<'MD'
        ---
        id: brief
        ---

        # Brief

        | A | B |
        |---|---|
        | 1 | 2 |

        - [x] Ready
        MD);

        $task = Task::create([
            'system' => 'signal7',
            'task_id' => 'S99',
            'folder_name' => 'S99-docs',
            'title' => 'Docs',
            'phase' => 'done',
            'scope' => 'strategy',
            'is_archived' => false,
        ]);

        $document = (new TaskDocumentService)->documentsFor($task)[0];

        $this->assertStringNotContainsString('id: brief', $document['body']);
        $this->assertStringContainsString('<table>', $document['html']);
        $this->assertStringContainsString('type="checkbox"', $document['html']);
    }

    private function rmTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;
            is_dir($path) ? $this->rmTree($path) : unlink($path);
        }

        rmdir($dir);
    }
}
