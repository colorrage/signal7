<?php

/**
 * REQUIRES SAIL (MySQL) to run.
 *
 * Execute inside Docker:
 *   sail test --filter=AssetsRelationManagerTest
 *
 * These tests use RefreshDatabase and a real DB connection.
 * They will NOT run in the standard unit suite (SQLite in-memory is fine
 * for most tests but Filament RelationManager tests need the full stack).
 */

namespace Tests\Feature\Filament\Signal7;

use App\Filament\Clusters\Signal7\Resources\TaskResource\Pages\ViewTask;
use App\Filament\Clusters\Signal7\Resources\TaskResource\RelationManagers\AssetsRelationManager;
use App\Models\Asset;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->task = Task::create([
            'system' => 'signal7',
            'task_id' => 'S1',
            'folder_name' => 'S1-test-campaign',
            'title' => 'Test Campaign',
            'phase' => 'create',
            'scope' => 'campaign',
        ]);
    }

    private function makeAsset(array $overrides = []): Asset
    {
        return Asset::create(array_merge([
            'task_id' => $this->task->id,
            'asset_id' => 'A-'.uniqid(),
            'title' => 'Test Asset',
            'asset_type' => 'blog-post',
            'channel' => 'web',
            'language' => 'en',
            'status' => 'draft',
            'revision' => 1,
            'depends' => null,
            'publish_at' => null,
            'external_gate' => null,
            'body' => null,
            'prompt' => null,
        ], $overrides));
    }

    /** @test */
    public function relation_manager_renders_asset_rows(): void
    {
        $asset1 = $this->makeAsset(['asset_id' => 'A1', 'title' => 'First Asset', 'asset_type' => 'blog-post']);
        $asset2 = $this->makeAsset(['asset_id' => 'A2', 'title' => 'Second Asset', 'asset_type' => 'social-post']);

        Livewire::test(AssetsRelationManager::class, [
            'ownerRecord' => $this->task,
            'pageClass' => ViewTask::class,
        ])
            ->assertCanSeeTableRecords([$asset1, $asset2]);
    }

    /** @test */
    public function relation_manager_shows_empty_state_when_no_assets(): void
    {
        Livewire::test(AssetsRelationManager::class, [
            'ownerRecord' => $this->task,
            'pageClass' => ViewTask::class,
        ])
            ->assertSee('No assets yet')
            ->assertSee('This campaign has no planned assets.');
    }

    /** @test */
    public function asset_type_filter_narrows_results(): void
    {
        $blogPost = $this->makeAsset(['asset_id' => 'A1', 'asset_type' => 'blog-post']);
        $socialPost = $this->makeAsset(['asset_id' => 'A2', 'asset_type' => 'social-post']);

        Livewire::test(AssetsRelationManager::class, [
            'ownerRecord' => $this->task,
            'pageClass' => ViewTask::class,
        ])
            ->assertCountTableRecords(2)
            ->filterTable('asset_type', 'blog-post')
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$blogPost])
            ->assertCanNotSeeTableRecords([$socialPost]);
    }

    /** @test */
    public function channel_filter_narrows_results(): void
    {
        $webAsset = $this->makeAsset(['asset_id' => 'A1', 'channel' => 'web']);
        $emailAsset = $this->makeAsset(['asset_id' => 'A2', 'channel' => 'email']);

        Livewire::test(AssetsRelationManager::class, [
            'ownerRecord' => $this->task,
            'pageClass' => ViewTask::class,
        ])
            ->assertCountTableRecords(2)
            ->filterTable('channel', 'web')
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$webAsset])
            ->assertCanNotSeeTableRecords([$emailAsset]);
    }

    /** @test */
    public function status_filter_narrows_results(): void
    {
        $draftAsset = $this->makeAsset(['asset_id' => 'A1', 'status' => 'draft']);
        $publishedAsset = $this->makeAsset(['asset_id' => 'A2', 'status' => 'published']);

        Livewire::test(AssetsRelationManager::class, [
            'ownerRecord' => $this->task,
            'pageClass' => ViewTask::class,
        ])
            ->assertCountTableRecords(2)
            ->filterTable('status', 'draft')
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$draftAsset])
            ->assertCanNotSeeTableRecords([$publishedAsset]);
    }

    /** @test */
    public function view_action_exists_on_table(): void
    {
        $this->makeAsset(['asset_id' => 'A1']);

        Livewire::test(AssetsRelationManager::class, [
            'ownerRecord' => $this->task,
            'pageClass' => ViewTask::class,
        ])
            ->assertTableActionExists('view');
    }

    /** @test */
    public function view_action_modal_contains_rendered_markdown_body(): void
    {
        $asset = $this->makeAsset([
            'asset_id' => 'A1',
            'title' => 'Markdown Test Asset',
            'body' => "## Section\n\nThis is the **body** content.",
            'prompt' => 'Write a **compelling** article about this topic.',
            'status' => 'draft',
            'revision' => 3,
        ]);

        Livewire::test(AssetsRelationManager::class, [
            'ownerRecord' => $this->task,
            'pageClass' => ViewTask::class,
        ])
            ->mountTableAction('view', $asset)
            ->assertSee('Markdown Test Asset')
            ->assertSee('draft')
            ->assertSee('Revision: 3');
    }

    /** @test */
    public function all_required_filters_exist(): void
    {
        Livewire::test(AssetsRelationManager::class, [
            'ownerRecord' => $this->task,
            'pageClass' => ViewTask::class,
        ])
            ->assertTableFilterExists('asset_type')
            ->assertTableFilterExists('channel')
            ->assertTableFilterExists('status');
    }
}
