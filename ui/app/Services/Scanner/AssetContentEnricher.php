<?php

namespace App\Services\Scanner;

use App\Models\Asset;
use App\Models\Task;
use App\Services\FrontmatterParser;
use Illuminate\Support\Facades\Log;

class AssetContentEnricher
{
    /**
     * For each asset belonging to $task, locate its A<N>-*.md file in $taskDir,
     * read the markdown body and the latest prompt file, then persist them into
     * the `assets.body` and `assets.prompt` columns. Idempotent.
     */
    public function enrich(string $taskDir, Task $task): int
    {
        if (! is_dir($taskDir)) {
            return 0;
        }

        $count = 0;
        $assets = Asset::where('task_id', $task->id)->get();

        foreach ($assets as $asset) {
            try {
                $body = $this->readAssetBody($taskDir, $asset->asset_id);
                $prompt = $this->readLatestPrompt($taskDir, $asset->asset_id);

                if ($body !== $asset->body || $prompt !== $asset->prompt) {
                    $asset->update(['body' => $body, 'prompt' => $prompt]);
                }

                $count++;
            } catch (\Throwable $e) {
                Log::warning("AssetContentEnricher: failed for asset {$asset->asset_id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Find A<N>-*.md in $taskDir and return the markdown body (below frontmatter).
     * Returns null when no file exists or the body is empty.
     */
    private function readAssetBody(string $taskDir, string $assetId): ?string
    {
        $file = $this->findAssetFile($taskDir, $assetId);
        if ($file === null) {
            return null;
        }

        $content = file_get_contents($file);
        $parsed = FrontmatterParser::parse($content);
        $body = trim($parsed['body']);

        return $body !== '' ? $body : null;
    }

    /**
     * Find the highest-revision prompt file for $assetId under $taskDir/prompts/
     * and return its full text, or null when none exists.
     *
     * Prompt filenames follow the Signal7 pattern:
     *   prompts/A<N>-r<revision>-<worker>.md
     */
    private function readLatestPrompt(string $taskDir, string $assetId): ?string
    {
        $promptsDir = $taskDir.'/prompts';
        if (! is_dir($promptsDir)) {
            return null;
        }

        $pattern = $promptsDir.'/'.$assetId.'-r*-*.md';
        $files = glob($pattern) ?: [];

        if (empty($files)) {
            return null;
        }

        // Sort descending so the highest-revision file comes first.
        // Filenames are sortable because revision numbers are zero-padded in practice,
        // but we use a natural-sort fallback to handle single-digit revisions gracefully.
        natsort($files);
        $latest = end($files);

        $content = file_get_contents($latest);

        return $content !== '' ? $content : null;
    }

    /**
     * Locate the first file matching A<N>-*.md in $taskDir.
     */
    private function findAssetFile(string $taskDir, string $assetId): ?string
    {
        $files = glob($taskDir.'/'.$assetId.'-*.md') ?: [];

        return $files[0] ?? null;
    }
}
