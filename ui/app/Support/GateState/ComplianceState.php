<?php

namespace App\Support\GateState;

final readonly class ComplianceState
{
    /**
     * @param  string  $status  One of: 'clear', 'questions-open', 'blocked'
     * @param  string[]  $openQuestions
     */
    public function __construct(
        public string $status,
        public array $openQuestions = [],
    ) {}

    /**
     * Returns true when publish should be blocked (status === 'blocked').
     */
    public function isPublishBlocked(): bool
    {
        return $this->status === 'blocked';
    }
}
