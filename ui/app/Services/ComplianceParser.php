<?php

namespace App\Services;

class ComplianceParser
{
    public static function parse(string $markdown): array
    {
        $result = ['status' => 'clear', 'open_questions' => []];

        $parsed = FrontmatterParser::parse($markdown);
        $frontmatter = $parsed['frontmatter'];
        $body = $parsed['body'];

        if (isset($frontmatter['status'])) {
            $result['status'] = $frontmatter['status'];
        }

        if (preg_match('/##\s*Open Questions\s*\n(.*?)(?=\n## |$)/s', $body, $matches)) {
            $section = trim($matches[1]);
            // The "none recorded" marker is its own paragraph (with optional italic
            // wrappers), not a substring inside a real question.
            $isNoneMarker = $section === ''
                || preg_match('/^_?\s*none recorded\s*_?$/i', $section) === 1;

            if (! $isNoneMarker) {
                $questions = array_filter(
                    array_map('trim', preg_split('/\n\s*[-*]\s+/', $section)),
                    fn ($q) => $q !== '' && $q !== '_none'
                );
                if (! empty($questions)) {
                    $result['open_questions'] = array_values($questions);
                    if ($result['status'] === 'clear') {
                        $result['status'] = 'questions-open';
                    }
                }
            }
        }

        return $result;
    }
}
