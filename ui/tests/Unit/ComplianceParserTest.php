<?php

namespace Tests\Unit;

use App\Services\ComplianceParser;
use PHPUnit\Framework\TestCase;

class ComplianceParserTest extends TestCase
{
    public function test_returns_clear_status_when_marked_clear(): void
    {
        $markdown = "---\nstatus: clear\nregulated_domain: false\n---\n\n# Compliance\n\n## Open Questions\n\n_none recorded_";
        $result = ComplianceParser::parse($markdown);

        $this->assertEquals('clear', $result['status']);
        $this->assertEmpty($result['open_questions']);
    }

    public function test_returns_questions_open_when_questions_exist(): void
    {
        $markdown = "---\nstatus: questions-open\n---\n\n# Compliance\n\n## Open Questions\n\n- Is this claim substantiated?\n- Did legal review the disclaimer?";
        $result = ComplianceParser::parse($markdown);

        $this->assertEquals('questions-open', $result['status']);
        $this->assertCount(2, $result['open_questions']);
        $this->assertStringContainsString('claim substantiated', $result['open_questions'][0]);
    }

    public function test_returns_blocked_status(): void
    {
        $markdown = "---\nstatus: blocked\n---\n\n# Compliance\n\n## Open Questions\n\n- Claim violates FDA guidelines";
        $result = ComplianceParser::parse($markdown);

        $this->assertEquals('blocked', $result['status']);
    }

    public function test_defaults_to_clear_when_no_status_in_frontmatter(): void
    {
        $markdown = "---\nregulated_domain: false\n---\n\n# Compliance\n\n## Open Questions\n\n_none recorded_";
        $result = ComplianceParser::parse($markdown);

        $this->assertEquals('clear', $result['status']);
    }

    public function test_handles_empty_open_questions_section(): void
    {
        $markdown = "---\nstatus: clear\n---\n\n# Compliance\n\n## Open Questions\n\n_none recorded_";
        $result = ComplianceParser::parse($markdown);

        $this->assertEmpty($result['open_questions']);
    }
}
