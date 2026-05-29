<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Str;

class TaskDocumentService
{
    /**
     * @return array<int, array{key: string, label: string, group: string, filename: string, content: string, body: string, html: string}>
     */
    public function documentsFor(Task $task): array
    {
        $taskDir = $task->diskFolderPath();
        if (! is_dir($taskDir)) {
            return [];
        }

        return $task->system === 'signal7'
            ? $this->signalDocuments($taskDir)
            : $this->hyperDocuments($taskDir, (string) $task->task_id);
    }

    /**
     * @return array<int, array{key: string, label: string, group: string, filename: string, content: string, body: string, html: string}>
     */
    private function hyperDocuments(string $taskDir, string $taskId): array
    {
        $documents = $this->knownDocuments($taskDir, [
            '03-technical-plan.md' => ['Technical Plan', 'Plans'],
            '04-execution-plan.md' => ['Execution Plan', 'Plans'],
            'research.md' => ['Research', 'Research'],
            'checks.md' => ['Checks', 'Verification'],
            'docs.md' => ['Docs', 'Docs'],
            'handoff.md' => ['Handoff', 'Handoff'],
        ]);

        foreach ($this->globSorted($taskDir.'/'.$taskId.'.*-*.md') as $file) {
            $documents[] = $this->documentFromFile($file, $this->labelFromFilename($file), 'Subtasks');
        }

        return $documents;
    }

    /**
     * @return array<int, array{key: string, label: string, group: string, filename: string, content: string, body: string, html: string}>
     */
    private function signalDocuments(string $taskDir): array
    {
        $documents = $this->knownDocuments($taskDir, [
            'brief.md' => ['Brief', 'Planning'],
            'content-plan.md' => ['Content Plan', 'Planning'],
            'review.md' => ['Review', 'Review'],
            'compliance.md' => ['Compliance', 'Compliance'],
            'research.md' => ['Research', 'Research'],
            'publish-log.md' => ['Publish Log', 'Publish'],
            'publish-ledger.md' => ['Publish Ledger', 'Publish'],
        ]);

        foreach ($this->globSorted($taskDir.'/publish*.md') as $file) {
            if ($this->alreadyIncluded($documents, $file)) {
                continue;
            }
            $documents[] = $this->documentFromFile($file, $this->labelFromFilename($file), 'Publish');
        }

        foreach ($this->globSorted($taskDir.'/*ledger*.md') as $file) {
            if ($this->alreadyIncluded($documents, $file)) {
                continue;
            }
            $documents[] = $this->documentFromFile($file, $this->labelFromFilename($file), 'Publish');
        }

        foreach ($this->globSorted($taskDir.'/A*-*.md') as $file) {
            $documents[] = $this->documentFromFile($file, $this->labelFromFilename($file), 'Assets / Research');
        }

        return $documents;
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $files
     * @return array<int, array{key: string, label: string, group: string, filename: string, content: string, body: string, html: string}>
     */
    private function knownDocuments(string $taskDir, array $files): array
    {
        $documents = [];

        foreach ($files as $filename => [$label, $group]) {
            $file = $taskDir.'/'.$filename;
            if (is_file($file)) {
                $documents[] = $this->documentFromFile($file, $label, $group);
            }
        }

        return $documents;
    }

    /**
     * @return array<int, string>
     */
    private function globSorted(string $pattern): array
    {
        $files = glob($pattern) ?: [];
        natsort($files);

        return array_values($files);
    }

    /**
     * @return array{key: string, label: string, group: string, filename: string, content: string, body: string, html: string}
     */
    private function documentFromFile(string $file, string $label, string $group): array
    {
        $content = (string) file_get_contents($file);
        $parsed = FrontmatterParser::parse($content);
        $body = trim($parsed['body']) !== '' ? $parsed['body'] : $content;

        return [
            'key' => md5($file),
            'label' => $label,
            'group' => $group,
            'filename' => basename($file),
            'content' => $content,
            'body' => $body,
            'html' => app(MarkdownDocumentRenderer::class)->toHtml($body),
        ];
    }

    private function labelFromFilename(string $file): string
    {
        $name = pathinfo($file, PATHINFO_FILENAME);
        $name = preg_replace('/^(T\d+\.\d+|A\d+)-/', '$1 ', $name) ?? $name;

        return Str::of($name)->replace('-', ' ')->title()->toString();
    }

    /**
     * @param  array<int, array{filename: string}>  $documents
     */
    private function alreadyIncluded(array $documents, string $file): bool
    {
        $filename = basename($file);

        foreach ($documents as $document) {
            if ($document['filename'] === $filename) {
                return true;
            }
        }

        return false;
    }
}
