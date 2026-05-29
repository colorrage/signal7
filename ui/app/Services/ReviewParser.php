<?php

namespace App\Services;

use App\Support\GateState\ApproverRow;

/**
 * Parse a review.md markdown file into a structured array.
 *
 * Mirrors ComplianceParser's shape: same module location, same static
 * parse(string $markdown): array signature, same tolerance for missing
 * sections and null values.
 */
class ReviewParser
{
    /**
     * Parse a review.md string into an array of ApproverRows and AI findings.
     *
     * Returns:
     * [
     *   'approvers'   => ApproverRow[],
     *   'ai_findings' => string[],
     * ]
     *
     * Tolerates:
     * - Empty / null input → returns empty approvers and findings
     * - Missing ## Approvers section → empty approvers list
     * - null / boolean / int / ISO datetime literals in bullet bodies
     * - Unknown extra fields: preserved on ApproverRow::$extra
     */
    public static function parse(string $markdown): array
    {
        $result = [
            'approvers' => [],
            'ai_findings' => [],
        ];

        if (trim($markdown) === '') {
            return $result;
        }

        // Extract ## AI Findings section
        if (preg_match('/##\s*AI Findings\s*\n(.*?)(?=\n##\s|\z)/s', $markdown, $matches)) {
            $section = trim($matches[1]);
            if ($section !== '') {
                $lines = array_filter(
                    array_map('trim', explode("\n", $section)),
                    fn ($line) => $line !== ''
                );
                // Strip leading "- " bullet markers
                $result['ai_findings'] = array_values(array_map(
                    fn ($line) => preg_replace('/^[-*]\s+/', '', $line),
                    $lines
                ));
            }
        }

        // Extract ## Approvers section
        if (! preg_match('/##\s*Approvers\s*\n(.*?)(?=\n##\s|\z)/s', $markdown, $matches)) {
            return $result;
        }

        $section = $matches[1];

        // Split on top-level approver blocks: lines starting with "- name:"
        // Each block starts at a "- name:" line.
        $blocks = preg_split('/(?=^- name:)/m', $section);

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            // Remove the leading "- " from the first line
            if (str_starts_with($block, '- ')) {
                $block = substr($block, 2);
            }

            $row = self::parseApproverBlock($block);
            if ($row !== null) {
                $result['approvers'][] = $row;
            }
        }

        return $result;
    }

    /**
     * Parse a single approver YAML-flavoured block into an ApproverRow.
     */
    private static function parseApproverBlock(string $block): ?ApproverRow
    {
        $knownFields = [
            'name', 'delegate', 'timeout_hours', 'auto_approve_on_timeout',
            'status', 'comment', 'rejected_reason', 'updated_at',
            'recorded_response',
        ];

        $fields = [];
        $extra = [];

        // Parse each "key: value" line from the block.
        // Lines may be indented (the YAML-flavoured bullet format uses 2-space indent).
        $lines = explode("\n", $block);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Match "key: value" (value may be quoted or bare)
            if (! preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*):\s*(.*)$/', $line, $m)) {
                continue;
            }

            $key = $m[1];
            $value = self::coerceValue(trim($m[2]));

            if (in_array($key, $knownFields, true)) {
                $fields[$key] = $value;
            } else {
                $extra[$key] = $value;
            }
        }

        if (! isset($fields['name'])) {
            return null;
        }

        // Parse updated_at as DateTimeImmutable if it's a non-null string
        $updatedAt = null;
        if (isset($fields['updated_at']) && is_string($fields['updated_at']) && $fields['updated_at'] !== '') {
            try {
                $updatedAt = new \DateTimeImmutable($fields['updated_at']);
            } catch (\Throwable) {
                $updatedAt = null;
            }
        }

        return new ApproverRow(
            name: (string) ($fields['name'] ?? ''),
            delegate: isset($fields['delegate']) && $fields['delegate'] !== null ? (string) $fields['delegate'] : null,
            timeout_hours: isset($fields['timeout_hours']) && $fields['timeout_hours'] !== null ? (int) $fields['timeout_hours'] : null,
            auto_approve_on_timeout: (bool) ($fields['auto_approve_on_timeout'] ?? false),
            status: (string) ($fields['status'] ?? 'pending'),
            comment: isset($fields['comment']) && $fields['comment'] !== null ? (string) $fields['comment'] : null,
            rejected_reason: isset($fields['rejected_reason']) && $fields['rejected_reason'] !== null ? (string) $fields['rejected_reason'] : null,
            updated_at: $updatedAt,
            recorded_response: isset($fields['recorded_response']) && $fields['recorded_response'] !== null ? (string) $fields['recorded_response'] : null,
            extra: $extra,
        );
    }

    /**
     * Coerce a raw YAML value string to the appropriate PHP type.
     */
    private static function coerceValue(string $raw): mixed
    {
        // Quoted string — strip quotes
        if (
            (str_starts_with($raw, '"') && str_ends_with($raw, '"')) ||
            (str_starts_with($raw, "'") && str_ends_with($raw, "'"))
        ) {
            return substr($raw, 1, -1);
        }

        if ($raw === 'null' || $raw === '') {
            return null;
        }

        if ($raw === 'true') {
            return true;
        }

        if ($raw === 'false') {
            return false;
        }

        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }

        return $raw;
    }
}
