<?php

namespace Tests\Unit;

use App\Services\ContentPlanParser;
use PHPUnit\Framework\TestCase;

class ContentPlanParserTest extends TestCase
{
    public function test_parses_markdown_table_into_asset_rows(): void
    {
        $markdown = "| asset_id | title | asset_type | channel | language | status | depends | publish_at |\n|----------|-------|------------|---------|----------|--------|---------|------------|\n| A1 | LinkedIn Launch Post | social-copy | linkedin | en | todo | | 2026-05-20 |\n| A2 | Email Newsletter | email-copy | email | en | todo | A1 | 2026-05-21 |";
        $rows = ContentPlanParser::parse($markdown);

        $this->assertCount(2, $rows);
        $this->assertEquals('A1', $rows[0]['asset_id']);
        $this->assertEquals('LinkedIn Launch Post', $rows[0]['title']);
        $this->assertEquals('social-copy', $rows[0]['asset_type']);
        $this->assertEquals('A2', $rows[1]['asset_id']);
        $this->assertEquals('A1', $rows[1]['depends']);
    }

    public function test_returns_empty_array_for_markdown_without_table(): void
    {
        $markdown = "# No Table Here\n\nJust some text.";
        $rows = ContentPlanParser::parse($markdown);

        $this->assertEmpty($rows);
    }

    public function test_returns_empty_array_for_empty_string(): void
    {
        $rows = ContentPlanParser::parse('');

        $this->assertEmpty($rows);
    }
}
