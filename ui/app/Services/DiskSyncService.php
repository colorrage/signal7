<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\BacklogEntry;
use App\Models\Recipe;
use App\Models\Task;
use App\Services\Scanner\AssetContentEnricher;
use App\Services\Scanner\SubtaskScanner;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DiskSyncService
{
    private string $signalBasePath;

    private string $hyperBasePath;

    public function __construct()
    {
        $this->signalBasePath = config('disksync.signal');
        $this->hyperBasePath = config('disksync.hyper');
    }

    public function syncAll(): array
    {
        return [
            'signal7' => $this->syncSystem('signal7'),
            'hyper7' => $this->syncSystem('hyper7'),
        ];
    }

    public function syncSystem(string $system): array
    {
        $basePath = $system === 'signal7' ? $this->signalBasePath : $this->hyperBasePath;
        $counts = ['tasks' => 0, 'assets' => 0, 'backlog' => 0, 'recipes' => 0];

        $this->syncTasks($system, $basePath, false, $counts);
        $this->syncTasks($system, $basePath, true, $counts);
        $this->syncBacklog($system, $basePath, $counts);
        $this->syncRecipes($system, $basePath, $counts);

        return $counts;
    }

    public function syncTask(string $system, string $taskId): void
    {
        $basePath = $system === 'signal7' ? $this->signalBasePath : $this->hyperBasePath;

        foreach (['/tasks', '/archive'] as $subdir) {
            $dir = $basePath.$subdir;
            if (! is_dir($dir)) {
                continue;
            }

            foreach (scandir($dir) as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $taskDir = $dir.'/'.$entry;
                if (! is_dir($taskDir)) {
                    continue;
                }
                if (! str_starts_with($entry, $taskId.'-')) {
                    continue;
                }

                $taskFile = $taskDir.'/task.md';
                if (! file_exists($taskFile)) {
                    continue;
                }

                $isArchived = $subdir === '/archive';
                try {
                    $task = $this->upsertTask($system, $taskFile, $entry, $isArchived);
                    if ($system === 'signal7') {
                        $this->syncAssets($taskDir, $task);
                        $this->syncCompliance($taskDir, $task);
                        (new AssetContentEnricher)->enrich($taskDir, $task);
                    }
                    if ($system === 'hyper7') {
                        (new SubtaskScanner)->scan($taskDir, $task);
                    }
                    $this->syncDashboardSummary($taskDir, $task);
                } catch (\Throwable $e) {
                    Log::warning("Failed to sync task: {$entry}", ['error' => $e->getMessage()]);
                }

                return;
            }
        }
    }

    private function syncTasks(string $system, string $basePath, bool $archived, array &$counts): void
    {
        $subdir = $archived ? '/archive' : '/tasks';
        $dir = $basePath.$subdir;

        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $taskDir = $dir.'/'.$entry;
            if (! is_dir($taskDir)) {
                continue;
            }

            $taskFile = $taskDir.'/task.md';
            if (! file_exists($taskFile)) {
                continue;
            }

            try {
                $task = $this->upsertTask($system, $taskFile, $entry, $archived);
                $counts['tasks']++;

                if ($system === 'signal7') {
                    $this->syncAssets($taskDir, $task, $counts);
                    $this->syncCompliance($taskDir, $task);
                    (new AssetContentEnricher)->enrich($taskDir, $task);
                }
                if ($system === 'hyper7') {
                    (new SubtaskScanner)->scan($taskDir, $task);
                }
                $this->syncDashboardSummary($taskDir, $task);
            } catch (\Throwable $e) {
                Log::warning("Failed to sync task: {$entry}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function upsertTask(string $system, string $taskFile, string $folderName, bool $isArchived): Task
    {
        $content = file_get_contents($taskFile);
        $parsed = FrontmatterParser::parse($content);
        $fm = $parsed['frontmatter'];

        return Task::updateOrCreate(
            ['system' => $system, 'task_id' => $fm['id'] ?? ''],
            [
                'folder_name' => $folderName,
                'title' => $fm['title'] ?? '',
                'phase' => $fm['phase'] ?? '',
                'scope' => $fm['scope'] ?? 'unknown',
                'is_archived' => $isArchived,
                'awaiting' => $fm['awaiting'] ?? null,
                'created_at_disk' => $fm['created'] ?? null,
            ]
        );
    }

    private function syncAssets(string $taskDir, Task $task, ?array &$counts = null): void
    {
        $contentPlanFile = $taskDir.'/content-plan.md';
        if (! file_exists($contentPlanFile)) {
            $this->syncAssetsFromMarkdownFiles($taskDir, $task, $counts);

            return;
        }

        $markdown = file_get_contents($contentPlanFile);
        $rows = ContentPlanParser::parse($markdown);
        $seenAssetIds = [];

        foreach ($rows as $row) {
            $assetId = $row['asset_id'] ?? $row['id'] ?? null;
            if (! $assetId) {
                continue;
            }
            $seenAssetIds[] = $assetId;

            $asset = Asset::updateOrCreate(
                ['task_id' => $task->id, 'asset_id' => $assetId],
                [
                    'title' => $row['title'] ?? '',
                    'asset_type' => $row['asset_type'] ?? '',
                    'channel' => $row['channel'] ?? null,
                    'language' => $row['language'] ?? null,
                    'status' => $row['status'] ?? 'todo',
                    'depends' => $this->parseDepends($row['depends'] ?? null),
                    'publish_at' => $this->nullableString($row['publish_at'] ?? null),
                ]
            );

            $this->syncAssetFrontmatter($taskDir, $task, $asset);

            if ($counts !== null) {
                $counts['assets']++;
            }
        }

        if ($seenAssetIds === []) {
            $this->syncAssetsFromMarkdownFiles($taskDir, $task, $counts);
        }
    }

    private function syncAssetsFromMarkdownFiles(string $taskDir, Task $task, ?array &$counts = null): void
    {
        $files = glob($taskDir.'/A*-*.md') ?: [];
        natsort($files);

        foreach ($files as $filePath) {
            $content = file_get_contents($filePath);
            if ($content === false) {
                continue;
            }

            $parsed = FrontmatterParser::parse($content);
            $frontmatter = $parsed['frontmatter'];
            $assetId = $frontmatter['id'] ?? $this->assetIdFromFilename($filePath);
            if (! is_string($assetId) || $assetId === '') {
                continue;
            }

            $asset = Asset::updateOrCreate(
                ['task_id' => $task->id, 'asset_id' => $assetId],
                [
                    'title' => $frontmatter['title'] ?? $this->titleFromAssetFilename($filePath),
                    'asset_type' => $frontmatter['asset_type'] ?? 'asset',
                    'channel' => $frontmatter['channel'] ?? null,
                    'language' => $frontmatter['language'] ?? null,
                    'status' => $frontmatter['status'] ?? 'done',
                    'revision' => $this->integerOrDefault($frontmatter['revision'] ?? null),
                    'depends' => $this->parseDepends($frontmatter['depends'] ?? null),
                    'publish_at' => $this->nullableString($frontmatter['publish_at'] ?? null),
                ]
            );

            $this->syncAssetFrontmatter($taskDir, $task, $asset);

            if ($counts !== null) {
                $counts['assets']++;
            }
        }
    }

    /**
     * Read the per-asset A*.md front-matter and persist `expires_at` and
     * `external_gate` onto the Asset row.
     *
     * Skips silently when:
     * - The A*.md file does not exist on disk (not an error — not every asset
     *   has a per-asset doc yet).
     * - The file's mtime is not newer than `asset.updated_at` (short-circuit
     *   to keep repeated syncs O(touched files)).
     *
     * `external_gate` is stored as a JSON-encoded string in the existing
     * nullable `string` column (column type conversion tracked separately).
     * The Asset model's `'external_gate' => 'array'` cast decodes it on read.
     */
    private function syncAssetFrontmatter(string $taskDir, Task $task, Asset $asset): void
    {
        // Resolve the per-asset file path: <taskDir>/A<id>-*.md
        // The asset_id is like "A1"; glob for A1-*.md inside the task dir.
        $pattern = $taskDir.'/'.$asset->asset_id.'-*.md';
        $matches = glob($pattern);

        if (empty($matches)) {
            return;
        }

        $filePath = $matches[0];

        // Short-circuit if the file has not been touched since the last sync.
        $mtime = filemtime($filePath);
        if ($mtime !== false && $asset->updated_at !== null) {
            $assetUpdatedAtTs = $asset->updated_at instanceof \DateTimeInterface
                ? $asset->updated_at->getTimestamp()
                : strtotime((string) $asset->updated_at);

            if ($mtime < $assetUpdatedAtTs) {
                return;
            }
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return;
        }

        $parsed = FrontmatterParser::parse($content);
        $frontmatter = $parsed['frontmatter'];

        // --- expires_at ---
        // FrontmatterParser coerces ISO-8601 date strings to integer UNIX timestamps.
        // We accept both integer timestamps and string representations.
        $expiresAt = null;
        $rawExpires = $frontmatter['expires_at'] ?? null;
        if ($rawExpires !== null && $rawExpires !== '') {
            try {
                if (is_int($rawExpires)) {
                    $expiresAt = Carbon::createFromTimestamp($rawExpires);
                } else {
                    $expiresAt = Carbon::parse((string) $rawExpires);
                }
            } catch (\Throwable) {
                $expiresAt = null;
            }
        }

        // --- external_gate ---
        // FrontmatterParser decodes JSON-object values directly into PHP arrays.
        $externalGateJson = null;
        $rawGate = $frontmatter['external_gate'] ?? null;
        if ($rawGate !== null && $rawGate !== '') {
            if (is_array($rawGate)) {
                $externalGateJson = $rawGate; // Asset cast handles JSON encoding on save
            } elseif (is_string($rawGate) && $rawGate !== '') {
                // Decode if it arrived as a raw JSON string
                $decoded = json_decode($rawGate, true);
                $externalGateJson = is_array($decoded) ? $decoded : ['raw' => $rawGate];
            }
        }

        $asset->expires_at = $expiresAt;
        $asset->external_gate = $externalGateJson;
        $asset->saveQuietly();
    }

    private function syncCompliance(string $taskDir, Task $task): void
    {
        $complianceFile = $taskDir.'/compliance.md';
        if (! file_exists($complianceFile)) {
            return;
        }

        $markdown = file_get_contents($complianceFile);
        $result = ComplianceParser::parse($markdown);

        if ($task->compliance_status !== $result['status']) {
            $task->update(['compliance_status' => $result['status']]);
        }
    }

    private function syncDashboardSummary(string $taskDir, Task $task): void
    {
        $summary = $this->extractDashboardSummary($taskDir);

        if ($task->dashboard_summary !== $summary) {
            $task->update(['dashboard_summary' => $summary]);
        }
    }

    private function extractDashboardSummary(string $taskDir): ?string
    {
        $dashboardFile = $taskDir.'/dashboard.md';

        if (file_exists($dashboardFile)) {
            $content = file_get_contents($dashboardFile);
            // Capture the first non-empty paragraph after the `## Goal` heading.
            // A paragraph ends at a blank line, the next heading, or end of file.
            if (preg_match('/##\s*Goal\s*\R+(\S[^\n]*(?:\n[^\n]+)*)/u', $content, $matches)) {
                return trim($matches[1]);
            }
        }

        $taskFile = $taskDir.'/task.md';
        if (file_exists($taskFile)) {
            $content = file_get_contents($taskFile);
            $parsed = FrontmatterParser::parse($content);
            $body = trim($parsed['body']);
            // Skip a leading H1 (the task title) and return the first paragraph after it.
            $body = preg_replace('/^#\s+[^\n]*\n+/', '', $body);
            $paragraphs = preg_split('/\n\s*\n/', (string) $body);
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if ($paragraph !== '' && ! str_starts_with($paragraph, '#')) {
                    return $paragraph;
                }
            }
        }

        return null;
    }

    private function assetIdFromFilename(string $filePath): ?string
    {
        return preg_match('/^(A\d+)-/', basename($filePath), $matches) ? $matches[1] : null;
    }

    private function titleFromAssetFilename(string $filePath): string
    {
        $name = pathinfo($filePath, PATHINFO_FILENAME);
        $name = preg_replace('/^A\d+-/', '', $name) ?? $name;

        return str($name)->replace('-', ' ')->title()->toString();
    }

    private function parseDepends(mixed $raw): ?array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }

        if (is_array($raw)) {
            $items = array_values(array_filter(array_map(
                fn ($value): string => trim((string) $value, " \t\n\r\0\x0B\"'"),
                $raw
            ), fn ($value) => $value !== ''));

            return $items === [] ? null : $items;
        }

        $normalized = trim((string) $raw);
        if ($normalized === '' || $normalized === '[]' || strtolower($normalized) === 'null') {
            return null;
        }

        if (str_starts_with($normalized, '[') && str_ends_with($normalized, ']')) {
            $normalized = trim($normalized, '[]');
        }

        $items = array_values(array_filter(array_map(
            fn ($value): string => trim((string) $value, " \t\n\r\0\x0B\"'"),
            explode(',', $normalized)
        ), fn ($value) => $value !== ''));

        return $items === [] ? null : $items;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' || strtolower($normalized) === 'null'
            ? null
            : $normalized;
    }

    private function integerOrDefault(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    private function syncBacklog(string $system, string $basePath, array &$counts): void
    {
        $backlogFile = $basePath.'/backlog.md';
        if (! file_exists($backlogFile)) {
            return;
        }

        $content = file_get_contents($backlogFile);

        // Match either `### B<N>: <title>` heading format or `- **B<N>** — <title>` bullet format.
        // The hyper-backlog skill writes bullets; older docs use the heading form. Be lenient.
        $entries = [];

        $headingPattern = '/^###\s+(B\d+):\s*(.+)$/m';
        $flags = PREG_OFFSET_CAPTURE | PREG_SET_ORDER;
        if (preg_match_all($headingPattern, $content, $headingMatches, $flags)) {
            foreach ($headingMatches as $i => $match) {
                $start = $match[0][1] + strlen($match[0][0]);
                $end = $headingMatches[$i + 1][0][1] ?? strlen($content);
                $entries[] = [
                    'entry_id' => $match[1][0],
                    'title' => trim($match[2][0]),
                    'description' => trim(substr($content, $start, $end - $start)) ?: null,
                ];
            }
        }

        $bulletPattern = '/^[-*]\s+\*\*(B\d+)\*\*\s*(?:—|-|:)?\s*(.+?)(?=\n[-*]\s+\*\*B\d+\*\*|\n##|\z)/msu';
        if (preg_match_all($bulletPattern, $content, $bulletMatches, PREG_SET_ORDER)) {
            foreach ($bulletMatches as $match) {
                $entryId = $match[1];
                if (! empty(array_filter($entries, fn ($e) => $e['entry_id'] === $entryId))) {
                    continue;
                }
                $body = trim($match[2]);
                // Bullet entries are often a single long paragraph. Split title from
                // description on the first sentence boundary (period followed by space),
                // falling back to a 200-char title limit when no boundary exists.
                $title = $body;
                $description = null;
                if (preg_match('/^(.{1,200}?[.!?])\s+(.+)$/su', $body, $parts)) {
                    $title = trim($parts[1]);
                    $description = trim($parts[2]) ?: null;
                } elseif (mb_strlen($body) > 200) {
                    $title = mb_substr($body, 0, 200);
                    $description = trim(mb_substr($body, 200)) ?: null;
                }
                $entries[] = [
                    'entry_id' => $entryId,
                    'title' => $title,
                    'description' => $description,
                ];
            }
        }

        foreach ($entries as $entry) {
            BacklogEntry::updateOrCreate(
                ['system' => $system, 'entry_id' => $entry['entry_id']],
                ['title' => $entry['title'], 'description' => $entry['description']]
            );
            $counts['backlog']++;
        }
    }

    private function syncRecipes(string $system, string $basePath, array &$counts): void
    {
        $recipesDir = $basePath.'/recipes';
        if (! is_dir($recipesDir)) {
            return;
        }

        foreach (scandir($recipesDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $recipeFile = $recipesDir.'/'.$entry;
            if (! is_file($recipeFile) || ! str_ends_with($entry, '.md')) {
                continue;
            }

            $content = file_get_contents($recipeFile);
            $parsed = FrontmatterParser::parse($content);
            $name = $parsed['frontmatter']['name'] ?? pathinfo($entry, PATHINFO_FILENAME);

            Recipe::updateOrCreate(
                ['system' => $system, 'filename' => $entry],
                ['name' => $name, 'content_hash' => md5($content)]
            );

            $counts['recipes']++;
        }
    }
}
