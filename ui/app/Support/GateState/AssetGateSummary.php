<?php

namespace App\Support\GateState;

final readonly class AssetGateSummary
{
    /**
     * @param  string  $assetId  The asset identifier (e.g. 'A1')
     * @param  array|null  $externalGate  Structured external gate data, or null
     * @param  \DateTimeImmutable|null  $expiresAt  Expiry timestamp, or null
     */
    public function __construct(
        public string $assetId,
        public ?array $externalGate,
        public ?\DateTimeImmutable $expiresAt,
    ) {}
}
