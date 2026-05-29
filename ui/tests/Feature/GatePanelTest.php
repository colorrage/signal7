<?php

namespace Tests\Feature;

use App\Contracts\CliCommandDispatcher;
use App\Livewire\GatePanel;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature tests for the GatePanel Livewire component.
 *
 * A fake CliCommandDispatcher is bound per test via the service container.
 * The PlaceholderCliCommandDispatcher throws on dispatch — tests use a
 * recording fake that captures calls without throwing.
 */
class GatePanelTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Fixtures — review.md content with one pending approver
    // -------------------------------------------------------------------------
    private const REVIEW_MD = <<<'MD'
# Review

## Approvers

- name: Owner
  delegate: null
  timeout_hours: 24
  auto_approve_on_timeout: false
  status: pending
  comment: null
  rejected_reason: null
  updated_at: null
  recorded_response: null
MD;

    private const REVIEW_MD_TWO_APPROVERS = <<<'MD'
# Review

## Approvers

- name: Alice
  delegate: null
  timeout_hours: 24
  auto_approve_on_timeout: false
  status: pending
  comment: null
  rejected_reason: null
  updated_at: null
  recorded_response: null

- name: Bob
  delegate: null
  timeout_hours: 48
  auto_approve_on_timeout: false
  status: approved
  comment: "looks good"
  rejected_reason: null
  updated_at: 2026-05-20T10:00:00
  recorded_response: "approve"
MD;

    private string $signalRoot;

    private string $taskDir;

    /** @var array<int, array{task_id: int|null, command: string, args: array}> */
    private array $dispatchCalls = [];

    /** @var array<string> Temp roots to remove in tearDown */
    private array $tempRoots = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Set up temp disk tree
        $base = sys_get_temp_dir().'/gate-panel-'.bin2hex(random_bytes(6));
        $this->signalRoot = $base.'/.signal';
        $this->taskDir = $this->signalRoot.'/tasks/S1-test-task';
        mkdir($this->taskDir, 0o755, true);
        $this->tempRoots[] = $base;
        config(['disksync.signal' => $this->signalRoot]);

        // Bind recording fake dispatcher
        $calls = &$this->dispatchCalls;
        $this->app->bind(CliCommandDispatcher::class, function () use (&$calls) {
            return new class($calls) implements CliCommandDispatcher
            {
                public function __construct(private array &$calls) {}

                public function dispatch(Task $task, string $command, array $args = []): string
                {
                    $this->calls[] = [
                        'task_id' => $task->id ?? null,
                        'command' => $command,
                        'args' => $args,
                    ];

                    return 'fake-dispatch-id-'.count($this->calls);
                }

                public function dispatchTaskless(string $command, array $args = []): string
                {
                    $this->calls[] = [
                        'task_id' => null,
                        'command' => $command,
                        'args' => $args,
                    ];

                    return 'fake-dispatch-id-'.count($this->calls);
                }
            };
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->tempRoots as $root) {
            $this->rmTree($root);
        }
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeTask(array $overrides = []): Task
    {
        return Task::create(array_merge([
            'system' => 'signal7',
            'task_id' => 'S1',
            'folder_name' => 'S1-test-task',
            'title' => 'Test Task',
            'phase' => 'review',
            'scope' => 'quick',
            'is_archived' => false,
            'awaiting' => null,
        ], $overrides));
    }

    private function writeReviewMd(string $content): void
    {
        file_put_contents($this->taskDir.'/review.md', $content);
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

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * When awaiting: null, the component renders nothing (outer guard works).
     */
    public function test_component_renders_nothing_when_awaiting_is_null(): void
    {
        $task = $this->makeTask(['awaiting' => null]);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->assertDontSee('Gate Panel')
            ->assertDontSee('Approve')
            ->assertDontSee('Submit');
    }

    /**
     * When awaiting: user-approval, Approve and Reject buttons are visible.
     */
    public function test_approve_and_reject_buttons_visible_for_user_approval(): void
    {
        $this->writeReviewMd(self::REVIEW_MD);
        $task = $this->makeTask(['awaiting' => 'user-approval']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->assertSee('Gate Panel')
            ->assertSee('Approve')
            ->assertSee('Reject');
    }

    /**
     * When awaiting: user-input, the text input and Submit button are visible.
     */
    public function test_text_input_and_submit_visible_for_user_input(): void
    {
        $task = $this->makeTask(['awaiting' => 'user-input']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->assertSee('Submit')
            ->assertSee('Enter your response');
    }

    /**
     * Calling approve() dispatches the correct command to the fake dispatcher.
     */
    public function test_approve_dispatches_correct_command(): void
    {
        $this->writeReviewMd(self::REVIEW_MD);
        $task = $this->makeTask(['awaiting' => 'user-approval']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->call('approve');

        $this->assertCount(1, $this->dispatchCalls);
        $this->assertSame('signal.approve', $this->dispatchCalls[0]['command']);
        $this->assertSame(['response' => 'continue'], $this->dispatchCalls[0]['args']);
        $this->assertSame($task->id, $this->dispatchCalls[0]['task_id']);
    }

    /**
     * Calling approve($name) passes the approver name as an arg.
     */
    public function test_approve_with_approver_name_passes_arg(): void
    {
        $this->writeReviewMd(self::REVIEW_MD_TWO_APPROVERS);
        $task = $this->makeTask(['awaiting' => 'user-approval']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->call('approve', 'Alice');

        $this->assertCount(1, $this->dispatchCalls);
        $this->assertSame('signal.approve', $this->dispatchCalls[0]['command']);
        $this->assertSame(['response' => 'continue', 'approver' => 'Alice'], $this->dispatchCalls[0]['args']);
    }

    /**
     * Reject with empty reason does not dispatch (validation).
     */
    public function test_reject_with_empty_reason_does_not_dispatch(): void
    {
        $this->writeReviewMd(self::REVIEW_MD);
        $task = $this->makeTask(['awaiting' => 'user-approval']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->set('rejectReason', '')
            ->call('reject');

        $this->assertCount(0, $this->dispatchCalls);
    }

    /**
     * Reject with a non-empty reason dispatches the correct command.
     */
    public function test_reject_with_reason_dispatches_correct_command(): void
    {
        $this->writeReviewMd(self::REVIEW_MD);
        $task = $this->makeTask(['awaiting' => 'user-approval']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->set('rejectReason', 'Brand guidelines not met')
            ->call('reject');

        $this->assertCount(1, $this->dispatchCalls);
        $this->assertSame('signal.run', $this->dispatchCalls[0]['command']);
        $this->assertSame(['input' => 'reject: Brand guidelines not met'], $this->dispatchCalls[0]['args']);
    }

    /**
     * Submit with non-empty userInput dispatches the correct command.
     */
    public function test_submit_dispatches_correct_command(): void
    {
        $task = $this->makeTask(['awaiting' => 'user-input']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->set('userInput', 'yes, proceed')
            ->call('submit');

        $this->assertCount(1, $this->dispatchCalls);
        $this->assertSame('signal.run', $this->dispatchCalls[0]['command']);
        $this->assertSame(['input' => 'yes, proceed'], $this->dispatchCalls[0]['args']);
    }

    /**
     * Submit with empty userInput does not dispatch (validation).
     */
    public function test_submit_with_empty_input_does_not_dispatch(): void
    {
        $task = $this->makeTask(['awaiting' => 'user-input']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->set('userInput', '')
            ->call('submit');

        $this->assertCount(0, $this->dispatchCalls);
    }

    /**
     * With two approvers, both names appear in the rendered output.
     */
    public function test_approver_rows_render_names_and_status(): void
    {
        $this->writeReviewMd(self::REVIEW_MD_TWO_APPROVERS);
        $task = $this->makeTask(['awaiting' => 'user-approval']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->assertSee('Alice')
            ->assertSee('Bob')
            ->assertSee('pending')
            ->assertSee('approved');
    }

    /**
     * Per-row Approve button calls approve($name) with the correct name.
     */
    public function test_per_row_approve_calls_approve_with_name(): void
    {
        $this->writeReviewMd(self::REVIEW_MD_TWO_APPROVERS);
        $task = $this->makeTask(['awaiting' => 'user-approval']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->call('approve', 'Alice');

        $this->assertCount(1, $this->dispatchCalls);
        $this->assertSame(['response' => 'continue', 'approver' => 'Alice'], $this->dispatchCalls[0]['args']);
    }

    /**
     * resolveCompliance() dispatches the canonical command string.
     */
    public function test_resolve_compliance_dispatches_correct_command(): void
    {
        $task = $this->makeTask(['awaiting' => 'user-approval']);

        $component = Livewire::test(GatePanel::class, ['task' => $task]);
        $command = $component->instance()->resolveComplianceCommand();
        $component->call('resolveCompliance');

        $this->assertCount(1, $this->dispatchCalls);
        $this->assertSame($command, $this->dispatchCalls[0]['command']);
    }
}
