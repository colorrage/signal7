<?php

namespace App\Support\GateState;

final readonly class ApproverRow
{
    public function __construct(
        public string $name,
        public ?string $delegate,
        public ?int $timeout_hours,
        public bool $auto_approve_on_timeout,
        public string $status,
        public ?string $comment,
        public ?string $rejected_reason,
        public ?\DateTimeImmutable $updated_at,
        public ?string $recorded_response,
        /** @var array<string, mixed> */
        public array $extra = [],
    ) {}

    /**
     * Compute the expiry timestamp for this approver row.
     *
     * Returns null when:
     * - timeout_hours is null (no deadline configured)
     * - status is 'approved' or 'rejected' (already resolved)
     */
    public function expiresAt(?\DateTimeImmutable $taskUpdatedAt): ?\DateTimeImmutable
    {
        if ($this->timeout_hours === null) {
            return null;
        }

        if (in_array($this->status, ['approved', 'rejected'], true)) {
            return null;
        }

        $anchor = $this->updated_at ?? $taskUpdatedAt;

        if ($anchor === null) {
            return null;
        }

        return $anchor->modify('+'.$this->timeout_hours.' hours');
    }
}
