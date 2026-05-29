<?php

namespace App\Services;

use App\Models\Asset;

class AssetRevisionService
{
    private const MAX_DIFF_BYTES = 51200;

    private const MAX_DIFF_LINES = 500;

    /**
     * @return array{available: bool, revisions: array<int, array<string, mixed>>, warnings: array<int, string>, message: string|null}
     */
    public function revisionsFor(Asset $asset): array
    {
        $context = $this->resolveContext($asset);
        if (! $context['available']) {
            return [
                'available' => false,
                'revisions' => [],
                'warnings' => $context['warnings'],
                'message' => $context['message'],
            ];
        }

        $promptsDir = $context['taskDir'].'/prompts';
        if (! is_dir($promptsDir)) {
            return [
                'available' => true,
                'revisions' => [],
                'warnings' => $context['warnings'],
                'message' => 'No prompt revisions found.',
            ];
        }

        $files = glob($promptsDir.'/'.$asset->asset_id.'-r*-*.md') ?: [];
        $warnings = $context['warnings'];
        $revisions = [];
        $seenRevisionNumbers = [];

        foreach ($files as $file) {
            $parsedName = $this->parsePromptFilename($file, (string) $asset->asset_id);
            if ($parsedName === null) {
                $warnings[] = 'Ignored malformed prompt filename: '.basename($file);

                continue;
            }

            if (isset($seenRevisionNumbers[$parsedName['revision']])) {
                $warnings[] = 'Duplicate prompt revision ignored: '.basename($file);

                continue;
            }
            $seenRevisionNumbers[$parsedName['revision']] = true;

            $raw = file_get_contents($file);
            if ($raw === false) {
                $warnings[] = 'Could not read prompt file: '.basename($file);

                continue;
            }

            $parsed = FrontmatterParser::parse($raw);
            $frontmatter = is_array($parsed['frontmatter']) ? $parsed['frontmatter'] : [];
            $body = trim((string) $parsed['body']);

            $revisions[] = [
                'revision' => $parsedName['revision'],
                'worker' => $parsedName['worker'],
                'file' => $file,
                'filename' => basename($file),
                'prompt' => $raw,
                'body' => $body !== '' ? $body : $raw,
                'model' => $this->stringValue($frontmatter['model'] ?? null),
                'worker_version' => $this->stringValue($frontmatter['worker_version'] ?? null),
                'prompt_hash' => $this->stringValue($frontmatter['prompt_hash'] ?? null),
                'content_hash' => $this->stringValue($frontmatter['content_hash'] ?? null),
                'timestamp' => $this->stringValue($frontmatter['timestamp'] ?? $frontmatter['created_at'] ?? null),
            ];
        }

        usort($revisions, fn (array $a, array $b): int => $a['revision'] <=> $b['revision']);

        return [
            'available' => true,
            'revisions' => $revisions,
            'warnings' => $warnings,
            'message' => $revisions === [] ? 'No valid prompt revisions found.' : null,
        ];
    }

    /**
     * @return array{available: bool, rows: array<int, array<string, string|null>>, warnings: array<int, string>, message: string|null}
     */
    public function generationLogFor(Asset $asset): array
    {
        $context = $this->resolveContext($asset);
        if (! $context['available']) {
            return [
                'available' => false,
                'rows' => [],
                'warnings' => $context['warnings'],
                'message' => $context['message'],
            ];
        }

        $assetFile = $this->resolveAssetFile($context['taskDir'], (string) $asset->asset_id);
        $warnings = array_merge($context['warnings'], $assetFile['warnings']);
        if ($assetFile['file'] === null) {
            return [
                'available' => false,
                'rows' => [],
                'warnings' => $warnings,
                'message' => 'Asset markdown file not found.',
            ];
        }

        $content = file_get_contents($assetFile['file']);
        if ($content === false) {
            return [
                'available' => false,
                'rows' => [],
                'warnings' => $warnings,
                'message' => 'Asset markdown file could not be read.',
            ];
        }

        $parsed = FrontmatterParser::parse($content);
        $frontmatter = is_array($parsed['frontmatter']) ? $parsed['frontmatter'] : [];
        $rawLog = $frontmatter['generation_log'] ?? null;

        if (! is_array($rawLog)) {
            $warnings[] = 'generation_log is missing or not a YAML array.';

            return [
                'available' => true,
                'rows' => [],
                'warnings' => $warnings,
                'message' => 'No generation log entries available.',
            ];
        }

        $rows = [];
        foreach ($rawLog as $entry) {
            if (! is_array($entry)) {
                $warnings[] = 'Ignored malformed generation_log entry.';

                continue;
            }

            $rows[] = [
                'model' => $this->stringValue($entry['model'] ?? null),
                'worker' => $this->stringValue($entry['worker'] ?? null),
                'worker_version' => $this->stringValue($entry['worker_version'] ?? null),
                'prompt_hash' => $this->stringValue($entry['prompt_hash'] ?? null),
                'content_hash' => $this->stringValue($entry['content_hash'] ?? null),
                'timestamp' => $this->stringValue($entry['timestamp'] ?? null),
            ];
        }

        return [
            'available' => true,
            'rows' => $rows,
            'warnings' => $warnings,
            'message' => $rows === [] ? 'No generation log entries available.' : null,
        ];
    }

    /**
     * @return array{available: bool, rows: array<int, array{type: string, left: string, right: string}>, warnings: array<int, string>, message: string|null, from: int|null, to: int|null}
     */
    public function diffFor(Asset $asset, ?int $fromRevision = null, ?int $toRevision = null): array
    {
        $revisionResult = $this->revisionsFor($asset);
        if (! $revisionResult['available'] || count($revisionResult['revisions']) < 2) {
            return [
                'available' => false,
                'rows' => [],
                'warnings' => $revisionResult['warnings'],
                'message' => $revisionResult['message'] ?? 'At least two revisions are required for comparison.',
                'from' => null,
                'to' => null,
            ];
        }

        $revisions = collect($revisionResult['revisions'])->keyBy('revision');
        $numbers = $revisions->keys()->sort()->values();
        $toRevision ??= (int) $numbers->last();
        $toIndex = $numbers->search($toRevision);
        $fromRevision ??= $toIndex !== false && $toIndex > 0
            ? (int) $numbers[$toIndex - 1]
            : (int) $numbers->first();

        $from = $revisions->get($fromRevision);
        $to = $revisions->get($toRevision);
        if ($from === null || $to === null || $fromRevision === $toRevision) {
            return [
                'available' => false,
                'rows' => [],
                'warnings' => $revisionResult['warnings'],
                'message' => 'Selected revisions are not available for comparison.',
                'from' => $fromRevision,
                'to' => $toRevision,
            ];
        }

        $left = $this->normalizeNewlines((string) $from['body']);
        $right = $this->normalizeNewlines((string) $to['body']);
        if ($this->tooLargeForDiff($left) || $this->tooLargeForDiff($right)) {
            return [
                'available' => false,
                'rows' => [],
                'warnings' => $revisionResult['warnings'],
                'message' => 'Diff unavailable for revisions above 500 lines or 50 KB.',
                'from' => $fromRevision,
                'to' => $toRevision,
            ];
        }

        return [
            'available' => true,
            'rows' => $this->lineDiff($left, $right),
            'warnings' => $revisionResult['warnings'],
            'message' => null,
            'from' => $fromRevision,
            'to' => $toRevision,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function promptFor(Asset $asset, ?int $revision = null): ?array
    {
        $result = $this->revisionsFor($asset);
        if ($result['revisions'] === []) {
            return null;
        }

        if ($revision === null) {
            return $result['revisions'][array_key_last($result['revisions'])];
        }

        foreach ($result['revisions'] as $row) {
            if ($row['revision'] === $revision) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array{available: bool, taskDir: string|null, warnings: array<int, string>, message: string|null}
     */
    private function resolveContext(Asset $asset): array
    {
        $asset->loadMissing('task');
        if ($asset->task === null) {
            return [
                'available' => false,
                'taskDir' => null,
                'warnings' => ['Asset has no owning task.'],
                'message' => 'Asset task is unavailable.',
            ];
        }

        $taskDir = $asset->task->diskFolderPath();
        if (! is_dir($taskDir)) {
            return [
                'available' => false,
                'taskDir' => $taskDir,
                'warnings' => ['Task folder does not exist: '.$taskDir],
                'message' => 'Task folder is unavailable.',
            ];
        }

        return [
            'available' => true,
            'taskDir' => $taskDir,
            'warnings' => [],
            'message' => null,
        ];
    }

    /**
     * @return array{file: string|null, warnings: array<int, string>}
     */
    private function resolveAssetFile(string $taskDir, string $assetId): array
    {
        $files = glob($taskDir.'/'.$assetId.'-*.md') ?: [];
        sort($files);
        $warnings = [];

        if (count($files) > 1) {
            $warnings[] = 'Multiple asset markdown files matched '.$assetId.'; using '.basename($files[0]).'.';
        }

        return [
            'file' => $files[0] ?? null,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{revision: int, worker: string}|null
     */
    private function parsePromptFilename(string $file, string $assetId): ?array
    {
        $quoted = preg_quote($assetId, '/');
        if (! preg_match('/^'.$quoted.'-r(\d+)-(.+)\.md$/', basename($file), $matches)) {
            return null;
        }

        return [
            'revision' => (int) $matches[1],
            'worker' => $matches[2],
        ];
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }

    private function normalizeNewlines(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }

    private function tooLargeForDiff(string $value): bool
    {
        return strlen($value) > self::MAX_DIFF_BYTES || substr_count($value, "\n") + 1 > self::MAX_DIFF_LINES;
    }

    /**
     * @return array<int, array{type: string, left: string, right: string}>
     */
    private function lineDiff(string $left, string $right): array
    {
        $a = $left === '' ? [] : explode("\n", $left);
        $b = $right === '' ? [] : explode("\n", $right);
        $m = count($a);
        $n = count($b);
        $lcs = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = $m - 1; $i >= 0; $i--) {
            for ($j = $n - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $rows = [];
        $i = 0;
        $j = 0;
        while ($i < $m && $j < $n) {
            if ($a[$i] === $b[$j]) {
                $rows[] = ['type' => 'unchanged', 'left' => $a[$i], 'right' => $b[$j]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $rows[] = ['type' => 'deleted', 'left' => $a[$i], 'right' => ''];
                $i++;
            } else {
                $rows[] = ['type' => 'added', 'left' => '', 'right' => $b[$j]];
                $j++;
            }
        }

        while ($i < $m) {
            $rows[] = ['type' => 'deleted', 'left' => $a[$i], 'right' => ''];
            $i++;
        }

        while ($j < $n) {
            $rows[] = ['type' => 'added', 'left' => '', 'right' => $b[$j]];
            $j++;
        }

        return $rows;
    }
}
