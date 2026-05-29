<?php

namespace App\Services;

use Symfony\Component\Yaml\Yaml;

class FrontmatterParser
{
    public static function parse(string $content): array
    {
        if (! str_starts_with(trim($content), '---')) {
            return ['frontmatter' => [], 'body' => $content];
        }

        $parts = preg_split('/^---\s*$/m', $content, 3);

        if (count($parts) < 3) {
            return ['frontmatter' => [], 'body' => $content];
        }

        $yaml = trim($parts[1]);
        $body = ltrim($parts[2]);

        try {
            $frontmatter = Yaml::parse($yaml) ?: [];
        } catch (\Throwable) {
            $frontmatter = self::parseSimpleKeyValue($yaml);
        }

        return ['frontmatter' => $frontmatter, 'body' => $body];
    }

    private static function parseSimpleKeyValue(string $yaml): array
    {
        $frontmatter = [];
        foreach (explode("\n", $yaml) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            if ($value === 'true') {
                $value = true;
            } elseif ($value === 'false') {
                $value = false;
            } elseif ($value === 'null' || $value === '') {
                $value = null;
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value)) {
                // Leave ISO datetimes as strings; Eloquent's 'datetime' cast parses them.
                // (The Yaml::parse() happy path also returns a string here.)
            } elseif (is_numeric($value)) {
                $value = str_contains($value, '.') ? (float) $value : (int) $value;
            }

            $frontmatter[$key] = $value;
        }

        return $frontmatter;
    }
}
