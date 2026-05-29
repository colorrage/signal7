<?php

namespace Tests\Feature\Scanner;

use App\Models\Asset;
use App\Models\Task;
use App\Services\Scanner\AssetContentEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Requires Sail (MySQL) to run. Execute inside Docker:
 *   sail test --filter=AssetContentEnricherTest
 */
class AssetContentEnricherTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDir;

    private Task $task;

    private AssetContentEnricher $enricher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/asset-enricher-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
        mkdir($this->tempDir.'/prompts', 0755, true);

        $this->task = Task::create([
            'system' => 'signal7',
            'task_id' => 'S99',
            'folder_name' => 'S99-test-campaign',
            'title' => 'Test Campaign',
            'phase' => 'create',
            'scope' => 'campaign',
        ]);

        $this->enricher = new AssetContentEnricher;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    private function createAsset(string $assetId): Asset
    {
        return Asset::create([
            'task_id' => $this->task->id,
            'asset_id' => $assetId,
            'title' => 'Test Asset',
            'asset_type' => 'social-copy',
            'status' => 'done',
        ]);
    }

    public function test_enriches_body_from_asset_file(): void
    {
        $this->createAsset('A1');
        file_put_contents($this->tempDir.'/A1-linkedin-en.md', implode("\n", [
            '---',
            'id: A1',
            'parent: S99',
            'status: done',
            '---',
            '',
            '## Content',
            '',
            'This is the generated content body.',
        ]));

        $this->enricher->enrich($this->tempDir, $this->task);

        $asset = Asset::where('asset_id', 'A1')->first();
        $this->assertStringContainsString('This is the generated content body.', $asset->body);
    }

    public function test_enriches_prompt_from_latest_prompt_file(): void
    {
        $this->createAsset('A1');
        file_put_contents($this->tempDir.'/A1-linkedin-en.md', "---\nid: A1\n---\n\nbody");
        file_put_contents($this->tempDir.'/prompts/A1-r0-signal-social.md', 'Prompt revision 0');
        file_put_contents($this->tempDir.'/prompts/A1-r1-signal-social.md', 'Prompt revision 1');

        $this->enricher->enrich($this->tempDir, $this->task);

        $asset = Asset::where('asset_id', 'A1')->first();
        $this->assertStringContainsString('Prompt revision 1', $asset->prompt);
    }

    public function test_enrich_is_idempotent(): void
    {
        $this->createAsset('A1');
        file_put_contents($this->tempDir.'/A1-en.md', "---\nid: A1\n---\n\nbody content");

        $this->enricher->enrich($this->tempDir, $this->task);
        $this->enricher->enrich($this->tempDir, $this->task);

        $this->assertCount(1, Asset::where('task_id', $this->task->id)->get());
    }

    public function test_null_prompt_when_no_prompt_file_exists(): void
    {
        $this->createAsset('A1');
        file_put_contents($this->tempDir.'/A1-en.md', "---\nid: A1\n---\n\nbody");

        $this->enricher->enrich($this->tempDir, $this->task);

        $asset = Asset::where('asset_id', 'A1')->first();
        $this->assertNull($asset->prompt);
    }

    public function test_null_body_when_no_asset_file_exists(): void
    {
        $this->createAsset('A1');
        // No file written — body should remain null.
        $this->enricher->enrich($this->tempDir, $this->task);

        $asset = Asset::where('asset_id', 'A1')->first();
        $this->assertNull($asset->body);
    }
}
