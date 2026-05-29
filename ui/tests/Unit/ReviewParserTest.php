<?php

namespace Tests\Unit;

use App\Services\ReviewParser;
use App\Support\GateState\ApproverRow;
use PHPUnit\Framework\TestCase;

class ReviewParserTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Fixture paths (relative to project root, resolved at test time)
    // ---------------------------------------------------------------------------

    private static function fixtureContent(string $relPath): string
    {
        // Local dev: dirname(__DIR__, 3) walks up from tests/Unit/ past ui/ to the project root.
        // Inside Sail: ui/ IS the container root, so we derive from SIGNAL7_BASE_PATH (.signal dir)
        // whose parent is the project root mounted at storage/project/signal7/.
        $signalBase = getenv('SIGNAL7_BASE_PATH');
        $projectRoot = $signalBase
            ? dirname($signalBase)         // .../storage/project/signal7
            : dirname(__DIR__, 3);         // .../signal7/signal7
        $path = $projectRoot.'/'.$relPath;

        if (! file_exists($path)) {
            return '';
        }

        return file_get_contents($path);
    }

    // ---------------------------------------------------------------------------
    // Round-trip fixture tests
    // ---------------------------------------------------------------------------

    public function test_publish_done_archive_fixture(): void
    {
        $markdown = self::fixtureContent(
            'evals/signal-fixtures/publish-done-archive/.signal/tasks/S1-publish-complete/review.md'
        );
        $result = ReviewParser::parse($markdown);

        $this->assertIsArray($result['approvers']);
        $this->assertCount(1, $result['approvers']);

        /** @var ApproverRow $row */
        $row = $result['approvers'][0];
        $this->assertInstanceOf(ApproverRow::class, $row);
        $this->assertSame('Owner', $row->name);
        $this->assertNull($row->delegate);
        $this->assertSame(24, $row->timeout_hours);
        $this->assertFalse($row->auto_approve_on_timeout);
        $this->assertSame('approved', $row->status);
        $this->assertSame('Looks good, ship it.', $row->comment);
        $this->assertNull($row->rejected_reason);
        $this->assertInstanceOf(\DateTimeImmutable::class, $row->updated_at);
        $this->assertSame('approve', $row->recorded_response);
    }

    public function test_quick_social_happy_fixture(): void
    {
        $markdown = self::fixtureContent(
            'evals/signal-fixtures/quick-social-happy/.signal/tasks/S1-quick-linkedin-launch/review.md'
        );
        $result = ReviewParser::parse($markdown);

        $this->assertCount(1, $result['approvers']);
        $row = $result['approvers'][0];
        $this->assertSame('Owner', $row->name);
        $this->assertSame('approved', $row->status);
        $this->assertSame('approved on linkedin tone', $row->comment);
    }

    public function test_review_redirect_create_fixture(): void
    {
        $markdown = self::fixtureContent(
            'evals/signal-fixtures/review-redirect-create/.signal/tasks/S1-review-revision-loop/review.md'
        );
        $result = ReviewParser::parse($markdown);

        $this->assertCount(1, $result['approvers']);
        $row = $result['approvers'][0];
        $this->assertSame('Owner', $row->name);
        $this->assertSame('pending', $row->status);
        $this->assertNull($row->comment);
        $this->assertNull($row->updated_at);
        $this->assertNull($row->recorded_response);
    }

    public function test_review_rejection_rework_fixture(): void
    {
        $markdown = self::fixtureContent(
            'evals/signal-fixtures/review-rejection-rework/.signal/tasks/S1-rejected-linkedin-post/review.md'
        );
        $result = ReviewParser::parse($markdown);

        $this->assertCount(1, $result['approvers']);
        $row = $result['approvers'][0];
        $this->assertSame('Owner', $row->name);
        $this->assertSame('pending', $row->status);
        $this->assertNull($row->comment);
        $this->assertNull($row->updated_at);
    }

    public function test_ai_findings_parsed(): void
    {
        $markdown = self::fixtureContent(
            'evals/signal-fixtures/publish-done-archive/.signal/tasks/S1-publish-complete/review.md'
        );
        $result = ReviewParser::parse($markdown);

        $this->assertNotEmpty($result['ai_findings']);
        // First finding should contain "Brand compliance"
        $this->assertStringContainsString('Brand compliance', $result['ai_findings'][0]);
    }

    // ---------------------------------------------------------------------------
    // Negative / tolerance cases
    // ---------------------------------------------------------------------------

    public function test_returns_empty_on_empty_string(): void
    {
        $result = ReviewParser::parse('');

        $this->assertSame([], $result['approvers']);
        $this->assertSame([], $result['ai_findings']);
    }

    public function test_returns_empty_on_whitespace_only(): void
    {
        $result = ReviewParser::parse('   ');

        $this->assertSame([], $result['approvers']);
    }

    public function test_missing_approvers_section_returns_empty_list(): void
    {
        $markdown = "# Review\n\n## AI Findings\n\n- All checks pass\n";
        $result = ReviewParser::parse($markdown);

        $this->assertSame([], $result['approvers']);
        $this->assertCount(1, $result['ai_findings']);
    }

    public function test_null_fields_are_tolerated(): void
    {
        $markdown = "# Review\n\n## Approvers\n\n- name: Tester\n  delegate: null\n  timeout_hours: null\n  auto_approve_on_timeout: false\n  status: pending\n  comment: null\n  rejected_reason: null\n  updated_at: null\n  recorded_response: null\n";
        $result = ReviewParser::parse($markdown);

        $this->assertCount(1, $result['approvers']);
        $row = $result['approvers'][0];
        $this->assertNull($row->delegate);
        $this->assertNull($row->timeout_hours);
        $this->assertNull($row->comment);
        $this->assertNull($row->updated_at);
        $this->assertNull($row->recorded_response);
    }

    public function test_auto_approve_on_timeout_true(): void
    {
        $markdown = "# Review\n\n## Approvers\n\n- name: AutoBot\n  delegate: null\n  timeout_hours: 48\n  auto_approve_on_timeout: true\n  status: pending\n  comment: null\n  rejected_reason: null\n  updated_at: null\n  recorded_response: null\n";
        $result = ReviewParser::parse($markdown);

        $this->assertCount(1, $result['approvers']);
        $this->assertTrue($result['approvers'][0]->auto_approve_on_timeout);
    }

    public function test_unknown_extra_field_is_preserved(): void
    {
        $markdown = "# Review\n\n## Approvers\n\n- name: Owner\n  delegate: null\n  timeout_hours: 24\n  auto_approve_on_timeout: false\n  status: pending\n  comment: null\n  rejected_reason: null\n  updated_at: null\n  recorded_response: null\n  custom_field: some_value\n";
        $result = ReviewParser::parse($markdown);

        $this->assertCount(1, $result['approvers']);
        $row = $result['approvers'][0];
        $this->assertArrayHasKey('custom_field', $row->extra);
        $this->assertSame('some_value', $row->extra['custom_field']);
    }

    public function test_multiple_approvers(): void
    {
        $markdown = "# Review\n\n## Approvers\n\n- name: Alice\n  delegate: null\n  timeout_hours: 24\n  auto_approve_on_timeout: false\n  status: approved\n  comment: \"LGTM\"\n  rejected_reason: null\n  updated_at: 2026-05-01T09:00:00\n  recorded_response: approve\n\n- name: Bob\n  delegate: null\n  timeout_hours: 48\n  auto_approve_on_timeout: true\n  status: pending\n  comment: null\n  rejected_reason: null\n  updated_at: null\n  recorded_response: null\n";
        $result = ReviewParser::parse($markdown);

        $this->assertCount(2, $result['approvers']);
        $this->assertSame('Alice', $result['approvers'][0]->name);
        $this->assertSame('Bob', $result['approvers'][1]->name);
        $this->assertSame('approved', $result['approvers'][0]->status);
        $this->assertSame('pending', $result['approvers'][1]->status);
    }
}
