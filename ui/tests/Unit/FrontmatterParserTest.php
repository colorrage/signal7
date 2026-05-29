<?php

namespace Tests\Unit;

use App\Services\FrontmatterParser;
use PHPUnit\Framework\TestCase;

class FrontmatterParserTest extends TestCase
{
    public function test_parses_yaml_frontmatter_and_body(): void
    {
        $content = "---\nid: T1\ntitle: Test Task\nphase: discover\n---\n\n# Test Task\n\nBody content here.";
        $result = FrontmatterParser::parse($content);

        $this->assertEquals(['id' => 'T1', 'title' => 'Test Task', 'phase' => 'discover'], $result['frontmatter']);
        $this->assertStringContainsString('# Test Task', $result['body']);
        $this->assertStringContainsString('Body content here', $result['body']);
    }

    public function test_handles_content_with_no_frontmatter(): void
    {
        $content = "# Just a heading\n\nSome body text.";
        $result = FrontmatterParser::parse($content);

        $this->assertEmpty($result['frontmatter']);
        $this->assertEquals($content, $result['body']);
    }

    public function test_handles_content_with_only_opening_delimiter(): void
    {
        $content = "---\nJust some text";
        $result = FrontmatterParser::parse($content);

        $this->assertEmpty($result['frontmatter']);
        $this->assertEquals($content, $result['body']);
    }

    public function test_handles_empty_frontmatter(): void
    {
        $content = "---\n---\n\n# Body only";
        $result = FrontmatterParser::parse($content);

        $this->assertEmpty($result['frontmatter']);
        $this->assertStringContainsString('# Body only', $result['body']);
    }

    public function test_falls_back_to_key_value_parsing_on_yaml_errors(): void
    {
        $content = "---\nid: T5\ntitle: Infrastructure & Scaffolding\nphase: implement\n---\n\n# Task Title";
        $result = FrontmatterParser::parse($content);

        $this->assertEquals('T5', $result['frontmatter']['id']);
        $this->assertEquals('Infrastructure & Scaffolding', $result['frontmatter']['title']);
        $this->assertEquals('implement', $result['frontmatter']['phase']);
    }
}
