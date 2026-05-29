<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Support\GateState\ApproverRow;
use App\Support\GateState\GateState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Task::getGateStateAttribute().
 *
 * Each test builds an isolated temp directory on disk, persists a Task row
 * pointing at that folder, and asserts the GateState VO shape returned by
 * the accessor.
 */
class TaskGateStateTest extends TestCase
{
    use RefreshDatabase;

    private string $signalRoot;

    private string $taskDir;

    /** @var array<string> Paths to clean up in tearDown */
    private array $tempRoots = [];

    // ---------------------------------------------------------------------------
    // Review.md contents lifted from evals/signal-fixtures/quick-social-happy
    // ---------------------------------------------------------------------------
    private const REVIEW_MD = <<<'MD'
# Review — S1

## AI Findings

- Brand compliance: pass
- Claims compliance: pass (no regulated claims; values match brief)
- Quality / channel fit: pass

## Approvers

- name: Owner
  delegate: null
  timeout_hours: 24
  auto_approve_on_timeout: false
  status: approved
  comment: "approved on linkedin tone"
  rejected_reason: null
  updated_at: 2026-04-30T10:25:00
  recorded_response: "approve"
MD;

    // ---------------------------------------------------------------------------
    // Compliance.md contents — clear status, no open questions
    // ---------------------------------------------------------------------------
    private const COMPLIANCE_CLEAR_MD = <<<'MD'
---
regulated_domain: false
jurisdictions: []
approved_claims_required: false
status: clear
---

# Compliance

## Regulated Domain

_not regulated_

## Claims Pre-check

_no regulated claims identified_

## Required Disclaimers

_none required_

## Jurisdiction Notes

_none recorded_

## Open Questions

_none recorded_
MD;

    // ---------------------------------------------------------------------------
    // Compliance.md contents — questions-open status
    // ---------------------------------------------------------------------------
    private const COMPLIANCE_QUESTIONS_MD = <<<'MD'
---
status: questions-open
---

# Compliance

## Open Questions

- Is the 40% figure audited?
- Does the claim require an SEC disclaimer?
MD;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().'/task-gate-'.bin2hex(random_bytes(6));
        $this->signalRoot = $base.'/.signal';
        $this->taskDir = $this->signalRoot.'/tasks/S1-test-task';

        mkdir($this->taskDir, 0o755, true);
        $this->tempRoots[] = $base;

        config(['disksync.signal' => $this->signalRoot]);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempRoots as $root) {
            $this->rmTree($root);
        }
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helper: create a Task row pointing at the temp task folder
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

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function test_gate_state_returns_gate_state_vo(): void
    {
        file_put_contents($this->taskDir.'/review.md', self::REVIEW_MD);
        file_put_contents($this->taskDir.'/compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask(['awaiting' => 'user-approval']);

        $state = $task->gate_state;

        $this->assertInstanceOf(GateState::class, $state);
        $this->assertSame('user-approval', $state->awaiting);
    }

    public function test_gate_state_parses_approvers_from_review_md(): void
    {
        file_put_contents($this->taskDir.'/review.md', self::REVIEW_MD);
        file_put_contents($this->taskDir.'/compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask();
        $state = $task->gate_state;

        $this->assertCount(1, $state->approvers);

        /** @var ApproverRow $approver */
        $approver = $state->approvers[0];
        $this->assertInstanceOf(ApproverRow::class, $approver);
        $this->assertSame('Owner', $approver->name);
        $this->assertSame('approved', $approver->status);
        $this->assertSame(24, $approver->timeout_hours);
        $this->assertFalse($approver->auto_approve_on_timeout);
        $this->assertSame('approved on linkedin tone', $approver->comment);
    }

    public function test_gate_state_parses_clear_compliance(): void
    {
        file_put_contents($this->taskDir.'/review.md', self::REVIEW_MD);
        file_put_contents($this->taskDir.'/compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask();
        $state = $task->gate_state;

        $this->assertSame('clear', $state->compliance->status);
        $this->assertSame([], $state->compliance->openQuestions);
        $this->assertFalse($state->compliance->isPublishBlocked());
    }

    public function test_gate_state_parses_questions_open_compliance(): void
    {
        file_put_contents($this->taskDir.'/review.md', self::REVIEW_MD);
        file_put_contents($this->taskDir.'/compliance.md', self::COMPLIANCE_QUESTIONS_MD);

        $task = $this->makeTask();
        $state = $task->gate_state;

        $this->assertSame('questions-open', $state->compliance->status);
        $this->assertNotEmpty($state->compliance->openQuestions);
        $this->assertFalse($state->compliance->isPublishBlocked());
    }

    public function test_gate_state_missing_review_md_returns_empty_approvers_without_raising(): void
    {
        // Only compliance.md present; review.md is intentionally absent
        file_put_contents($this->taskDir.'/compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask();
        $state = $task->gate_state;

        $this->assertInstanceOf(GateState::class, $state);
        $this->assertSame([], $state->approvers);
    }

    public function test_gate_state_missing_compliance_md_returns_clear_compliance_without_raising(): void
    {
        // Only review.md present; compliance.md is intentionally absent
        file_put_contents($this->taskDir.'/review.md', self::REVIEW_MD);

        $task = $this->makeTask();
        $state = $task->gate_state;

        $this->assertInstanceOf(GateState::class, $state);
        $this->assertSame('clear', $state->compliance->status);
    }

    public function test_gate_state_both_files_missing_returns_empty_default_shape(): void
    {
        // Neither review.md nor compliance.md present
        $task = $this->makeTask();
        $state = $task->gate_state;

        $this->assertInstanceOf(GateState::class, $state);
        $this->assertSame([], $state->approvers);
        $this->assertSame('clear', $state->compliance->status);
        $this->assertSame([], $state->compliance->openQuestions);
        $this->assertSame([], $state->assets);
    }

    public function test_gate_state_asset_summaries_are_empty_when_no_assets_loaded(): void
    {
        file_put_contents($this->taskDir.'/review.md', self::REVIEW_MD);
        file_put_contents($this->taskDir.'/compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask();
        // assets relation is NOT loaded — summaries default to []
        $state = $task->gate_state;

        $this->assertSame([], $state->assets);
    }

    public function test_gate_state_is_memoised_per_request(): void
    {
        file_put_contents($this->taskDir.'/review.md', self::REVIEW_MD);
        file_put_contents($this->taskDir.'/compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask();
        $state1 = $task->gate_state;
        $state2 = $task->gate_state;

        // Same object instance returned on repeated reads
        $this->assertSame($state1, $state2);
    }

    public function test_refresh_clears_gate_state_cache(): void
    {
        file_put_contents($this->taskDir.'/review.md', self::REVIEW_MD);
        file_put_contents($this->taskDir.'/compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask();
        $state1 = $task->gate_state;

        $task->refresh();
        $state2 = $task->gate_state;

        // After refresh() the cache is cleared; a new VO is returned
        $this->assertNotSame($state1, $state2);
        // But the values should still match
        $this->assertSame($state1->awaiting, $state2->awaiting);
    }

    public function test_awaiting_passthrough_reflects_model_column(): void
    {
        file_put_contents($this->taskDir.'/review.md', self::REVIEW_MD);
        file_put_contents($this->taskDir.'/compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask(['awaiting' => 'user-input']);
        $state = $task->gate_state;

        $this->assertSame('user-input', $state->awaiting);
    }

    public function test_awaiting_null_when_model_column_is_null(): void
    {
        file_put_contents($this->taskDir.'/review.md', self::REVIEW_MD);
        file_put_contents($this->taskDir.'/compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask(['awaiting' => null]);
        $state = $task->gate_state;

        $this->assertNull($state->awaiting);
    }

    public function test_archived_task_resolves_disk_path_under_archive_subdir(): void
    {
        // Create the task folder under /archive instead of /tasks
        $archiveDir = $this->signalRoot.'/archive/S1-test-task';
        mkdir($archiveDir, 0o755, true);
        file_put_contents($archiveDir.'/review.md', self::REVIEW_MD);
        file_put_contents($archiveDir.'/compliance.md', self::COMPLIANCE_CLEAR_MD);

        $task = $this->makeTask(['is_archived' => true]);
        $state = $task->gate_state;

        $this->assertInstanceOf(GateState::class, $state);
        $this->assertCount(1, $state->approvers);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

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
