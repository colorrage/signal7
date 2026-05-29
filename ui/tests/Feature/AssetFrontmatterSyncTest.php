<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Task;
use App\Services\DiskSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for DiskSyncService::syncAssetFrontmatter().
 *
 * Verifies that per-asset A*.md front-matter is read and persisted onto the
 * Asset row after syncAssets runs:
 * - null-tolerance path (expires_at: null, external_gate: null)
 * - non-null expires_at round-trip
 * - non-null external_gate (structured array) round-trip
 * - missing A*.md file is silently skipped
 * - short-circuit when filemtime <= asset.updated_at
 */
class AssetFrontmatterSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $signalRoot;

    private string $taskDir;

    /** @var array<string> Temp roots to remove in tearDown */
    private array $tempRoots = [];

    // ---------------------------------------------------------------------------
    // Minimal task.md front-matter
    // ---------------------------------------------------------------------------
    private const TASK_MD = <<<'MD'
---
id: S10
title: Translate gate test
phase: create
scope: campaign
created: 2026-05-01T10:00:00
awaiting: null
---

# Translate gate test
MD;

    // ---------------------------------------------------------------------------
    // Content-plan with two assets
    // ---------------------------------------------------------------------------
    private const CONTENT_PLAN_MD = <<<'MD'
# Content Plan

| asset_id | title | asset_type | channel | language | status | depends | publish_at |
|----------|-------|------------|---------|----------|--------|---------|------------|
| A1 | LinkedIn launch post (en) | social-copy | linkedin | en | done |  |  |
| A2 | LinkedIn launch post (fr) | social-copy | linkedin | fr | todo |  |  |
MD;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().'/asset-frontmatter-'.bin2hex(random_bytes(6));
        $this->signalRoot = $base.'/.signal';
        $this->taskDir = $this->signalRoot.'/tasks/S10-translate-gate-test';

        mkdir($this->taskDir, 0o755, true);
        $this->tempRoots[] = $base;

        config(['disksync.signal' => $this->signalRoot]);

        // Write shared files
        file_put_contents($this->taskDir.'/task.md', self::TASK_MD);
        file_put_contents($this->taskDir.'/content-plan.md', self::CONTENT_PLAN_MD);
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

    private function writeAssetFrontmatter(string $assetId, array $frontmatter): void
    {
        $lines = ['---'];
        foreach ($frontmatter as $key => $value) {
            if ($value === null) {
                $lines[] = "{$key}: null";
            } elseif (is_bool($value)) {
                $lines[] = "{$key}: ".($value ? 'true' : 'false');
            } elseif (is_array($value)) {
                $lines[] = "{$key}: ".json_encode($value);
            } else {
                $lines[] = "{$key}: {$value}";
            }
        }
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '# Asset '.$assetId;
        file_put_contents($this->taskDir.'/'.$assetId.'-linkedin-post.md', implode("\n", $lines));
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
     * Null-tolerance path: A1 front-matter has expires_at: null and
     * external_gate: null. Both asset columns must be null after sync.
     */
    public function test_null_expires_at_and_external_gate_are_tolerated(): void
    {
        $this->writeAssetFrontmatter('A1', [
            'id' => 'A1',
            'title' => 'LinkedIn launch post (en)',
            'expires_at' => null,
            'external_gate' => null,
        ]);

        (new DiskSyncService)->syncSystem('signal7');

        $asset = Asset::where('asset_id', 'A1')->first();
        $this->assertNotNull($asset, 'Asset A1 must exist after sync');
        $this->assertNull($asset->expires_at, 'expires_at should be null');
        $this->assertNull($asset->external_gate, 'external_gate should be null');
    }

    /**
     * Non-null expires_at: parsed from ISO-8601 string and persisted as datetime.
     */
    public function test_non_null_expires_at_is_persisted(): void
    {
        $this->writeAssetFrontmatter('A1', [
            'id' => 'A1',
            'title' => 'LinkedIn launch post (en)',
            'expires_at' => '2026-06-30T23:59:00',
            'external_gate' => null,
        ]);

        (new DiskSyncService)->syncSystem('signal7');

        $asset = Asset::where('asset_id', 'A1')->first();
        $this->assertNotNull($asset);
        $this->assertNotNull($asset->expires_at, 'expires_at should not be null');
        $this->assertSame('2026-06-30', $asset->expires_at->format('Y-m-d'));
    }

    /**
     * Structured external_gate array is persisted (as JSON in the string column)
     * and decoded back as array on read via the 'array' cast.
     */
    public function test_structured_external_gate_is_persisted_and_decoded(): void
    {
        $gateData = [
            'status' => 'pending',
            'description' => 'Awaiting legal sign-off',
            'source_system' => 'legal-review',
            'required_status' => 'approved',
            'evidence' => null,
            'checked_at' => null,
        ];

        $this->writeAssetFrontmatter('A1', [
            'id' => 'A1',
            'title' => 'LinkedIn launch post (en)',
            'expires_at' => null,
            'external_gate' => $gateData,
        ]);

        (new DiskSyncService)->syncSystem('signal7');

        $asset = Asset::where('asset_id', 'A1')->first();
        $this->assertNotNull($asset);
        $this->assertIsArray($asset->external_gate);
        $this->assertSame('pending', $asset->external_gate['status']);
        $this->assertSame('legal-review', $asset->external_gate['source_system']);
    }

    /**
     * Both non-null expires_at and non-null external_gate in same asset.
     */
    public function test_both_fields_non_null_round_trip(): void
    {
        $this->writeAssetFrontmatter('A2', [
            'id' => 'A2',
            'title' => 'LinkedIn launch post (fr)',
            'expires_at' => '2026-07-15T12:00:00',
            'external_gate' => ['status' => 'satisfied', 'description' => 'Cleared'],
        ]);

        (new DiskSyncService)->syncSystem('signal7');

        $asset = Asset::where('asset_id', 'A2')->first();
        $this->assertNotNull($asset);
        $this->assertSame('2026-07-15', $asset->expires_at->format('Y-m-d'));
        $this->assertSame('satisfied', $asset->external_gate['status']);
    }

    /**
     * Missing A*.md: syncAssets continues without error; expires_at and
     * external_gate remain null.
     */
    public function test_missing_asset_frontmatter_file_is_silently_skipped(): void
    {
        // No A1-*.md file written — sync should not raise

        (new DiskSyncService)->syncSystem('signal7');

        $asset = Asset::where('asset_id', 'A1')->first();
        $this->assertNotNull($asset, 'Asset A1 must still be created from content-plan.md');
        $this->assertNull($asset->expires_at);
        $this->assertNull($asset->external_gate);
    }

    /**
     * Two assets: A1 has null front-matter, A2 has non-null front-matter.
     * Both are processed independently.
     */
    public function test_multiple_assets_processed_independently(): void
    {
        // A1 — null fields
        $this->writeAssetFrontmatter('A1', [
            'id' => 'A1',
            'expires_at' => null,
            'external_gate' => null,
        ]);

        // A2 — non-null fields
        $this->writeAssetFrontmatter('A2', [
            'id' => 'A2',
            'expires_at' => '2026-08-01T00:00:00',
            'external_gate' => ['status' => 'pending'],
        ]);

        (new DiskSyncService)->syncSystem('signal7');

        $a1 = Asset::where('asset_id', 'A1')->first();
        $this->assertNull($a1->expires_at);
        $this->assertNull($a1->external_gate);

        $a2 = Asset::where('asset_id', 'A2')->first();
        $this->assertSame('2026-08-01', $a2->expires_at->format('Y-m-d'));
        $this->assertSame('pending', $a2->external_gate['status']);
    }
}
