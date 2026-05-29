<?php

namespace Tests\Unit;

use App\Support\GateState\ApproverRow;
use App\Support\GateState\AssetGateSummary;
use App\Support\GateState\ComplianceState;
use App\Support\GateState\GateState;
use PHPUnit\Framework\TestCase;

class GateStateTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // ApproverRow::expiresAt()
    // ---------------------------------------------------------------------------

    public function test_expires_at_computed_from_row_updated_at(): void
    {
        $updatedAt = new \DateTimeImmutable('2026-05-01T10:00:00');
        $row = new ApproverRow(
            name: 'Owner',
            delegate: null,
            timeout_hours: 24,
            auto_approve_on_timeout: false,
            status: 'pending',
            comment: null,
            rejected_reason: null,
            updated_at: $updatedAt,
            recorded_response: null,
        );

        $expiresAt = $row->expiresAt(null);

        $this->assertInstanceOf(\DateTimeImmutable::class, $expiresAt);
        $expected = $updatedAt->modify('+24 hours');
        $this->assertEquals($expected, $expiresAt);
    }

    public function test_expires_at_falls_back_to_task_updated_at_when_row_updated_at_null(): void
    {
        $taskUpdatedAt = new \DateTimeImmutable('2026-05-01T08:00:00');
        $row = new ApproverRow(
            name: 'Owner',
            delegate: null,
            timeout_hours: 12,
            auto_approve_on_timeout: false,
            status: 'pending',
            comment: null,
            rejected_reason: null,
            updated_at: null,
            recorded_response: null,
        );

        $expiresAt = $row->expiresAt($taskUpdatedAt);

        $this->assertInstanceOf(\DateTimeImmutable::class, $expiresAt);
        $expected = $taskUpdatedAt->modify('+12 hours');
        $this->assertEquals($expected, $expiresAt);
    }

    public function test_expires_at_returns_null_when_status_approved(): void
    {
        $row = new ApproverRow(
            name: 'Owner',
            delegate: null,
            timeout_hours: 24,
            auto_approve_on_timeout: false,
            status: 'approved',
            comment: 'LGTM',
            rejected_reason: null,
            updated_at: new \DateTimeImmutable('2026-05-01T10:00:00'),
            recorded_response: 'approve',
        );

        $this->assertNull($row->expiresAt(null));
    }

    public function test_expires_at_returns_null_when_status_rejected(): void
    {
        $row = new ApproverRow(
            name: 'Owner',
            delegate: null,
            timeout_hours: 24,
            auto_approve_on_timeout: false,
            status: 'rejected',
            comment: null,
            rejected_reason: 'Off-brand',
            updated_at: new \DateTimeImmutable('2026-05-01T10:00:00'),
            recorded_response: null,
        );

        $this->assertNull($row->expiresAt(null));
    }

    public function test_expires_at_returns_null_when_timeout_hours_null(): void
    {
        $row = new ApproverRow(
            name: 'Owner',
            delegate: null,
            timeout_hours: null,
            auto_approve_on_timeout: false,
            status: 'pending',
            comment: null,
            rejected_reason: null,
            updated_at: new \DateTimeImmutable('2026-05-01T10:00:00'),
            recorded_response: null,
        );

        $this->assertNull($row->expiresAt(null));
    }

    public function test_expires_at_returns_null_when_both_updated_ats_null(): void
    {
        $row = new ApproverRow(
            name: 'Owner',
            delegate: null,
            timeout_hours: 24,
            auto_approve_on_timeout: false,
            status: 'pending',
            comment: null,
            rejected_reason: null,
            updated_at: null,
            recorded_response: null,
        );

        $this->assertNull($row->expiresAt(null));
    }

    // ---------------------------------------------------------------------------
    // ComplianceState::isPublishBlocked()
    // ---------------------------------------------------------------------------

    public function test_is_publish_blocked_returns_true_only_on_blocked(): void
    {
        $blocked = new ComplianceState('blocked');
        $clear = new ComplianceState('clear');
        $questionsOpen = new ComplianceState('questions-open', ['Q1']);

        $this->assertTrue($blocked->isPublishBlocked());
        $this->assertFalse($clear->isPublishBlocked());
        $this->assertFalse($questionsOpen->isPublishBlocked());
    }

    // ---------------------------------------------------------------------------
    // GateState::hasOpenApprovers()
    // ---------------------------------------------------------------------------

    public function test_has_open_approvers_true_when_any_pending(): void
    {
        $pending = new ApproverRow('A', null, 24, false, 'pending', null, null, null, null);
        $approved = new ApproverRow('B', null, 24, false, 'approved', 'LGTM', null, null, 'approve');

        $gs = new GateState(
            awaiting: 'user-approval',
            approvers: [$pending, $approved],
            compliance: new ComplianceState('clear'),
        );

        $this->assertTrue($gs->hasOpenApprovers());
    }

    public function test_has_open_approvers_false_when_all_approved(): void
    {
        $approved = new ApproverRow('A', null, 24, false, 'approved', null, null, null, 'approve');

        $gs = new GateState(
            awaiting: null,
            approvers: [$approved],
            compliance: new ComplianceState('clear'),
        );

        $this->assertFalse($gs->hasOpenApprovers());
    }

    public function test_has_open_approvers_false_when_no_approvers(): void
    {
        $gs = new GateState(
            awaiting: null,
            approvers: [],
            compliance: new ComplianceState('clear'),
        );

        $this->assertFalse($gs->hasOpenApprovers());
    }

    // ---------------------------------------------------------------------------
    // GateState::toArray() round-trip
    // ---------------------------------------------------------------------------

    public function test_to_array_round_trips(): void
    {
        $updatedAt = new \DateTimeImmutable('2026-05-01T10:00:00+00:00');
        $approver = new ApproverRow(
            name: 'Owner',
            delegate: null,
            timeout_hours: 24,
            auto_approve_on_timeout: false,
            status: 'pending',
            comment: null,
            rejected_reason: null,
            updated_at: $updatedAt,
            recorded_response: null,
        );

        $expiresAt = new \DateTimeImmutable('2026-05-02T12:00:00+00:00');
        $asset = new AssetGateSummary(
            assetId: 'A1',
            externalGate: ['status' => 'pending', 'source_system' => 'Legal'],
            expiresAt: $expiresAt,
        );

        $gs = new GateState(
            awaiting: 'user-approval',
            approvers: [$approver],
            compliance: new ComplianceState('questions-open', ['Is this claim substantiated?']),
            assets: [$asset],
        );

        $arr = $gs->toArray();

        $this->assertSame('user-approval', $arr['awaiting']);
        $this->assertCount(1, $arr['approvers']);
        $this->assertSame('Owner', $arr['approvers'][0]['name']);
        $this->assertSame('pending', $arr['approvers'][0]['status']);
        $this->assertSame('questions-open', $arr['compliance']['status']);
        $this->assertSame(['Is this claim substantiated?'], $arr['compliance']['openQuestions']);
        $this->assertCount(1, $arr['assets']);
        $this->assertSame('A1', $arr['assets'][0]['assetId']);
        $this->assertSame('pending', $arr['assets'][0]['externalGate']['status']);
    }
}
