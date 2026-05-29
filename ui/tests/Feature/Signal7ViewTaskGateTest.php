<?php

namespace Tests\Feature;

use App\Contracts\CliCommandDispatcher;
use App\Filament\Clusters\Signal7\Resources\TaskResource\Pages\ViewTask;
use App\Livewire\GatePanel;
use App\Models\Asset;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * End-to-end feature tests for the Signal7 ViewTask gate surface.
 *
 * Scenarios 1-7 test GatePanel (the primary gate action component embedded
 * in the ViewTask infolist) via direct Livewire::test(GatePanel::class).
 * Scenario 8 tests the full ViewTask page render via HTTP + actingAs.
 *
 * A fake CliCommandDispatcher is bound in setUp() to capture dispatch calls
 * without actually starting background processes.
 *
 * Database: SQLite in-memory (set via DB_CONNECTION/DB_DATABASE env vars).
 */
class Signal7ViewTaskGateTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Fixtures
    // -------------------------------------------------------------------------

    private const REVIEW_MD_SINGLE = <<<'MD'
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

    private const COMPLIANCE_CLEAR_MD = <<<'MD'
---
status: clear
---

# Compliance

## Open Questions

_none recorded_
MD;

    private const COMPLIANCE_QUESTIONS_MD = <<<'MD'
---
status: questions-open
---

# Compliance

## Open Questions

- Is the 40% figure audited?
- Does the claim require an SEC disclaimer?
MD;

    private const COMPLIANCE_BLOCKED_MD = <<<'MD'
---
status: blocked
---

# Compliance

Blocked pending legal review.

## Open Questions

_none recorded_
MD;

    private string $signalRoot;

    private string $taskDir;

    /** @var array<int, array{task_id: int|null, command: string, args: array}> */
    private array $dispatchCalls = [];

    /** @var array<string> Temp roots to clean up in tearDown */
    private array $tempRoots = [];

    // -------------------------------------------------------------------------
    // Set-up / tear-down
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        // Build an isolated temp disk tree.
        $base = sys_get_temp_dir().'/s7viewtask-'.bin2hex(random_bytes(6));
        $this->signalRoot = $base.'/.signal';
        $this->taskDir = $this->signalRoot.'/tasks/S1-test-task';
        mkdir($this->taskDir, 0o755, true);
        $this->tempRoots[] = $base;
        config(['disksync.signal' => $this->signalRoot]);

        // Bind a recording fake dispatcher — no real process is started.
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

                    return 'fake-'.count($this->calls);
                }

                public function dispatchTaskless(string $command, array $args = []): string
                {
                    $this->calls[] = [
                        'task_id' => null,
                        'command' => $command,
                        'args' => $args,
                    ];

                    return 'fake-'.count($this->calls);
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
            'title' => 'Test Campaign',
            'phase' => 'review',
            'scope' => 'quick',
            'is_archived' => false,
            'awaiting' => null,
        ], $overrides));
    }

    private function makeAsset(Task $task, array $overrides = []): Asset
    {
        return Asset::create(array_merge([
            'task_id' => $task->id,
            'asset_id' => 'A1',
            'title' => 'Test Asset',
            'asset_type' => 'blog-post',
            'channel' => 'web',
            'language' => 'en',
            'status' => 'draft',
            'revision' => 1,
            'depends' => null,
            'publish_at' => null,
            'external_gate' => null,
            'expires_at' => null,
            'body' => null,
            'prompt' => null,
        ], $overrides));
    }

    private function writeFile(string $filename, string $content): void
    {
        file_put_contents($this->taskDir.'/'.$filename, $content);
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

    // =========================================================================
    // Scenario 1: awaiting: user-approval
    //   GatePanel mounts, Approve and Reject buttons visible, Approve dispatches
    // =========================================================================

    /**
     * With awaiting: user-approval the GatePanel renders Approve and Reject buttons.
     */
    public function test_user_approval_gate_shows_approve_and_reject_buttons(): void
    {
        $this->writeFile('review.md', self::REVIEW_MD_SINGLE);
        $this->writeFile('compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask(['awaiting' => 'user-approval']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->assertSee('Gate Panel')
            ->assertSee('Approve')
            ->assertSee('Reject');
    }

    /**
     * Clicking Approve calls the fake dispatcher with the registry approve key.
     */
    public function test_user_approval_approve_dispatches_correct_command(): void
    {
        $this->writeFile('review.md', self::REVIEW_MD_SINGLE);
        $this->writeFile('compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask(['awaiting' => 'user-approval']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->call('approve');

        $this->assertCount(1, $this->dispatchCalls);
        $this->assertSame('signal.approve', $this->dispatchCalls[0]['command']);
        $this->assertSame($task->id, $this->dispatchCalls[0]['task_id']);
    }

    // =========================================================================
    // Scenario 2: awaiting: user-input
    //   Text input + Submit button visible; Submit calls dispatcher with input
    // =========================================================================

    /**
     * With awaiting: user-input the GatePanel renders a text input and Submit button.
     */
    public function test_user_input_gate_shows_text_input_and_submit(): void
    {
        $task = $this->makeTask(['awaiting' => 'user-input']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->assertSee('Submit')
            ->assertSee('Enter your response');
    }

    /**
     * Submitting user input calls the dispatcher with the typed string.
     */
    public function test_user_input_submit_dispatches_with_user_text(): void
    {
        $task = $this->makeTask(['awaiting' => 'user-input']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->set('userInput', 'yes, proceed')
            ->call('submit');

        $this->assertCount(1, $this->dispatchCalls);
        $this->assertSame('signal.run', $this->dispatchCalls[0]['command']);
        $this->assertSame(['input' => 'yes, proceed'], $this->dispatchCalls[0]['args']);
    }

    // =========================================================================
    // Scenario 3: awaiting: null
    //   GatePanel outer guard hides the action surface
    // =========================================================================

    /**
     * When awaiting is null the GatePanel renders no action surface.
     */
    public function test_null_awaiting_hides_gate_panel_surface(): void
    {
        $task = $this->makeTask(['awaiting' => null]);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->assertDontSee('Gate Panel')
            ->assertDontSee('Approve')
            ->assertDontSee('Submit');
    }

    // =========================================================================
    // Scenario 4: Approver rows
    //   Multiple approvers render with name, status, timeout; per-row Approve
    // =========================================================================

    /**
     * Approver rows render both names and statuses from review.md.
     */
    public function test_approver_rows_render_all_approver_names_and_statuses(): void
    {
        $this->writeFile('review.md', self::REVIEW_MD_TWO_APPROVERS);
        $this->writeFile('compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask(['awaiting' => 'user-approval']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->assertSee('Alice')
            ->assertSee('Bob')
            ->assertSee('pending')
            ->assertSee('approved');
    }

    /**
     * Per-row Approve button dispatches with the correct approver name.
     */
    public function test_per_row_approve_passes_approver_name_to_dispatcher(): void
    {
        $this->writeFile('review.md', self::REVIEW_MD_TWO_APPROVERS);
        $this->writeFile('compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask(['awaiting' => 'user-approval']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->call('approve', 'Alice');

        $this->assertCount(1, $this->dispatchCalls);
        $this->assertSame(['response' => 'continue', 'approver' => 'Alice'], $this->dispatchCalls[0]['args']);
    }

    // =========================================================================
    // Scenario 5: Compliance banner — clear
    //   Banner not rendered; Publish header action is enabled
    // =========================================================================

    /**
     * When compliance is clear the banner is not rendered.
     */
    public function test_compliance_clear_hides_banner(): void
    {
        $this->writeFile('review.md', self::REVIEW_MD_SINGLE);
        $this->writeFile('compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask(['awaiting' => 'user-approval']);

        // Banner is rendered by GatePanel — test via GatePanel component
        Livewire::test(GatePanel::class, ['task' => $task])
            ->assertDontSee('Compliance Blocked')
            ->assertDontSee('Compliance Questions Open');
    }

    /**
     * When compliance is clear the publish action is not blocked.
     */
    public function test_compliance_clear_publish_not_blocked(): void
    {
        $this->writeFile('review.md', self::REVIEW_MD_SINGLE);
        $this->writeFile('compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask(['awaiting' => 'user-approval']);

        $this->assertFalse($task->gate_state->compliance->isPublishBlocked());
    }

    // =========================================================================
    // Scenario 6: Compliance banner — questions-open
    //   Yellow banner lists open questions; Resolve Compliance button visible
    // =========================================================================

    /**
     * When compliance is questions-open the banner shows open questions.
     */
    public function test_compliance_questions_open_renders_yellow_banner_with_questions(): void
    {
        $this->writeFile('review.md', self::REVIEW_MD_SINGLE);
        $this->writeFile('compliance.md', self::COMPLIANCE_QUESTIONS_MD);

        $task = $this->makeTask(['awaiting' => 'user-approval']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->assertSee('Compliance Questions Open')
            ->assertSee('Is the 40% figure audited')
            ->assertSee('Resolve Compliance');
    }

    /**
     * When compliance is questions-open publish is still enabled.
     */
    public function test_compliance_questions_open_does_not_block_publish(): void
    {
        $this->writeFile('review.md', self::REVIEW_MD_SINGLE);
        $this->writeFile('compliance.md', self::COMPLIANCE_QUESTIONS_MD);

        $task = $this->makeTask(['awaiting' => 'user-approval']);

        $this->assertFalse($task->gate_state->compliance->isPublishBlocked());
    }

    // =========================================================================
    // Scenario 7: Compliance banner — blocked
    //   Red banner rendered; isPublishBlocked() returns true
    // =========================================================================

    /**
     * When compliance is blocked the banner signals publish is disabled.
     */
    public function test_compliance_blocked_renders_red_banner(): void
    {
        $this->writeFile('review.md', self::REVIEW_MD_SINGLE);
        $this->writeFile('compliance.md', self::COMPLIANCE_BLOCKED_MD);

        $task = $this->makeTask(['awaiting' => 'user-approval']);

        Livewire::test(GatePanel::class, ['task' => $task])
            ->assertSee('Compliance Blocked')
            ->assertSee('Resolve Compliance');
    }

    /**
     * When compliance is blocked isPublishBlocked() returns true.
     */
    public function test_compliance_blocked_publish_is_blocked(): void
    {
        $this->writeFile('review.md', self::REVIEW_MD_SINGLE);
        $this->writeFile('compliance.md', self::COMPLIANCE_BLOCKED_MD);

        $task = $this->makeTask(['awaiting' => 'user-approval']);

        $this->assertTrue($task->gate_state->compliance->isPublishBlocked());
    }

    // =========================================================================
    // Scenario 8: Asset chips
    //   ViewTask page renders external_gate and expires_at chips when populated;
    //   assets without those fields render without chips.
    //
    //   Tested via Livewire::actingAs($user)->test(ViewTask::class) which boots
    //   the Filament page as a Livewire component (bypassing HTTP middleware but
    //   respecting the authorizeAccess() check with the authenticated user).
    // =========================================================================

    /**
     * ViewTask page renders the external_gate badge for an asset that has one.
     */
    public function test_asset_with_external_gate_renders_chip_in_view_task(): void
    {
        $this->writeFile('review.md', self::REVIEW_MD_SINGLE);
        $this->writeFile('compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask(['awaiting' => null]);
        $this->makeAsset($task, [
            'asset_id' => 'A1',
            'title' => 'Gated Asset',
            'external_gate' => ['status' => 'pending', 'description' => 'Legal review required'],
            'expires_at' => now()->addDays(30)->toDateTimeString(),
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ViewTask::class, ['record' => $task->getKey()])
            ->assertSee('Gated Asset')
            ->assertSee('pending')    // external_gate status badge
            ->assertSee('Expires');   // expires_at label
    }

    /**
     * ViewTask page renders an asset row without gate chips when both
     * external_gate and expires_at are null.
     */
    public function test_asset_without_gate_fields_renders_without_chips(): void
    {
        $this->writeFile('review.md', self::REVIEW_MD_SINGLE);
        $this->writeFile('compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask(['awaiting' => null]);
        $this->makeAsset($task, [
            'asset_id' => 'A2',
            'title' => 'Plain Asset',
            'external_gate' => null,
            'expires_at' => null,
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ViewTask::class, ['record' => $task->getKey()])
            ->assertSee('Plain Asset')
            ->assertDontSee('Ext. Gate') // chip column absent
            ->assertDontSee('Expires');  // expires_at chip absent
    }
}
