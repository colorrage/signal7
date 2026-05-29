<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\MarkdownParser;

class ContentPlanParser
{
    public static function parse(string $markdown): array
    {
        $config = ['html_input' => 'escape'];
        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);

        $converter = new MarkdownParser($environment);
        $document = $converter->parse($markdown);

        $table = self::findTable($document);
        if (! $table) {
            return [];
        }

        return self::parseTable($table);
    }

    private static function findTable(Document $document): ?Table
    {
        $walker = $document->walker();
        while ($event = $walker->next()) {
            $node = $event->getNode();
            if ($event->isEntering() && $node instanceof Table) {
                return $node;
            }
        }

        return null;
    }

    /**
     * In league/commonmark v2 the Table is composed of one or more TableSection
     * children (a head and zero-or-more body sections); each TableSection
     * contains TableRow children, and each TableRow contains TableCell children.
     * There is no distinct TableHead/TableBody subclass — sections are
     * distinguished by TableSection::isHead() / isBody().
     */
    private static function parseTable(Table $table): array
    {
        $headerKeys = [];
        $rows = [];

        foreach ($table->children() as $section) {
            if (! $section instanceof TableSection) {
                continue;
            }

            $isHead = $section->isHead();

            foreach ($section->children() as $row) {
                if (! $row instanceof TableRow) {
                    continue;
                }

                $cells = [];
                foreach ($row->children() as $cell) {
                    if ($cell instanceof TableCell) {
                        $cells[] = trim(self::getCellText($cell));
                    }
                }

                if ($isHead) {
                    $headerKeys = array_map('strtolower', $cells);

                    continue;
                }

                if (empty($headerKeys) || count($cells) !== count($headerKeys)) {
                    continue;
                }

                $rows[] = array_combine($headerKeys, $cells);
            }
        }

        return $rows;
    }

    private static function getCellText(TableCell $cell): string
    {
        $text = '';
        $walker = $cell->walker();
        while ($event = $walker->next()) {
            $node = $event->getNode();
            if ($event->isEntering() && $node instanceof Text) {
                $text .= $node->getLiteral();
            }
        }

        return $text;
    }
}
