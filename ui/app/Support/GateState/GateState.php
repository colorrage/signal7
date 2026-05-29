<?php

namespace App\Support\GateState;

final readonly class GateState
{
    /**
     * @param  string|null  $awaiting  Current awaiting state (e.g. 'user-approval', 'user-input', null)
     * @param  ApproverRow[]  $approvers  List of parsed approver rows
     * @param  ComplianceState  $compliance  Compliance state VO
     * @param  AssetGateSummary[]  $assets  Per-asset gate summaries
     */
    public function __construct(
        public ?string $awaiting,
        public array $approvers,
        public ComplianceState $compliance,
        public array $assets = [],
    ) {}

    /**
     * Returns true when any approver has status 'pending'.
     */
    public function hasOpenApprovers(): bool
    {
        foreach ($this->approvers as $approver) {
            if ($approver->status === 'pending') {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a plain array representation for debugging and test assertions.
     */
    public function toArray(): array
    {
        return [
            'awaiting' => $this->awaiting,
            'approvers' => array_map(fn (ApproverRow $row) => [
                'name' => $row->name,
                'delegate' => $row->delegate,
                'timeout_hours' => $row->timeout_hours,
                'auto_approve_on_timeout' => $row->auto_approve_on_timeout,
                'status' => $row->status,
                'comment' => $row->comment,
                'rejected_reason' => $row->rejected_reason,
                'updated_at' => $row->updated_at?->format(\DateTimeInterface::ATOM),
                'recorded_response' => $row->recorded_response,
                'extra' => $row->extra,
            ], $this->approvers),
            'compliance' => [
                'status' => $this->compliance->status,
                'openQuestions' => $this->compliance->openQuestions,
            ],
            'assets' => array_map(fn (AssetGateSummary $s) => [
                'assetId' => $s->assetId,
                'externalGate' => $s->externalGate,
                'expiresAt' => $s->expiresAt?->format(\DateTimeInterface::ATOM),
            ], $this->assets),
        ];
    }
}
