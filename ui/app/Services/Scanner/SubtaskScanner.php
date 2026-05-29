<?php

namespace App\Services\Scanner;

use App\Models\Subtask;
use App\Models\Task;
use App\Services\FrontmatterParser;
use Illuminate\Support\Facades\Log;

class SubtaskScanner
{
    /**
     * Scan a Hyper7 task directory for T<N>.<M>-*.md subtask files and
     * upsert matching rows into the `subtasks` table. Idempotent.
     */
    public function scan(string $taskDir, Task $task): int
    {
        $files = $this->findSubtaskFiles($taskDir, $task->task_id);

        $seenIds = [];
        $position = 0;

        foreach ($files as $filePath) {
            try {
                $content = file_get_contents($filePath);
                $parsed = FrontmatterParser::parse($content);
                $fm = $parsed['frontmatter'];
                $body = trim($parsed['body']);

                $subtaskId = $fm['id'] ?? null;
                if (! $subtaskId) {
                    continue;
                }

                // parent_subtask_id is only set when the parent field names another subtask
                // (e.g. T11.1 inside a deeper nesting). Top-level subtasks have parent == task id.
                $parentFm = $fm['parent'] ?? null;
                $parentSubtaskId = (is_string($parentFm) && str_contains($parentFm, '.'))
                    ? $parentFm
                    : null;

                Subtask::updateOrCreate(
                    ['task_id' => $task->id, 'subtask_id' => $subtaskId],
                    [
                        'parent_subtask_id' => $parentSubtaskId,
                        'title' => $fm['title'] ?? '',
                        'status' => $fm['status'] ?? 'todo',
                        'awaiting' => $fm['awaiting'] ?? null,
                        'role' => $fm['role'] ?? null,
                        'depends' => $this->parseList($fm['depends'] ?? null),
                        'writes' => $this->parseList($fm['writes'] ?? null),
                        'body' => $body ?: null,
                        'position' => $position,
                    ]
                );

                $seenIds[] = $subtaskId;
                $position++;
            } catch (\Throwable $e) {
                Log::warning("SubtaskScanner: failed to parse {$filePath}", ['error' => $e->getMessage()]);
            }
        }

        // Remove orphaned rows whose backing file no longer exists.
        if (! empty($seenIds)) {
            Subtask::where('task_id', $task->id)
                ->whereNotIn('subtask_id', $seenIds)
                ->delete();
        }

        return count($seenIds);
    }

    /**
     * Return subtask .md files sorted by filename (stable source order).
     */
    private function findSubtaskFiles(string $taskDir, string $taskId): array
    {
        if (! is_dir($taskDir)) {
            return [];
        }

        // Subtask filenames match T<task_num>.<subtask_num>-<slug>.md
        // e.g. T11.1-data-layer.md
        $pattern = $taskDir.'/'.preg_quote($taskId, '/').'.\d+-*.md';
        $files = glob($taskDir.'/'.$taskId.'.*-*.md') ?: [];
        sort($files);

        return $files;
    }

    /**
     * Normalise a frontmatter list value to a PHP array or null.
     * Handles YAML arrays (already decoded by FrontmatterParser as arrays),
     * comma-separated strings, and null/empty values.
     */
    private function parseList(mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            $filtered = array_values(array_filter(array_map('trim', $value)));

            return $filtered === [] ? null : $filtered;
        }

        if (is_string($value)) {
            // YAML inline list: ["T11.1", "T11.2"]
            if (str_starts_with(trim($value), '[')) {
                $inner = trim($value, " \t[]");
                $items = array_map(fn ($s) => trim($s, " \t\"'"), explode(',', $inner));
                $filtered = array_values(array_filter($items));

                return $filtered === [] ? null : $filtered;
            }
            // Comma-separated plain string
            $items = array_values(array_filter(array_map('trim', explode(',', $value))));

            return $items === [] ? null : $items;
        }

        return null;
    }
}
