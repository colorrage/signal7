<?php

namespace Tests\Unit\Models;

use App\Models\Subtask;
use PHPUnit\Framework\TestCase;

/**
 * Pure-unit tests — no DB required. DB-backed (relation, scope) tests run
 * inside Sail as part of T11.2/T11.3 feature verification.
 */
class SubtaskTest extends TestCase
{
    public function test_depends_is_cast_to_array(): void
    {
        $subtask = new Subtask(['depends' => ['T1.1', 'T1.2']]);
        $this->assertIsArray($subtask->depends);
        $this->assertEquals(['T1.1', 'T1.2'], $subtask->depends);
    }

    public function test_writes_is_cast_to_array(): void
    {
        $subtask = new Subtask(['writes' => ['app/Models/Foo.php']]);
        $this->assertIsArray($subtask->writes);
        $this->assertEquals(['app/Models/Foo.php'], $subtask->writes);
    }

    public function test_empty_json_columns_cast_to_null(): void
    {
        $subtask = new Subtask;
        $this->assertNull($subtask->depends);
        $this->assertNull($subtask->writes);
    }

    public function test_fillable_includes_all_expected_fields(): void
    {
        $subtask = new Subtask;
        $fillable = $subtask->getFillable();

        foreach (['task_id', 'subtask_id', 'parent_subtask_id', 'title',
            'status', 'awaiting', 'role', 'depends', 'writes', 'body', 'position'] as $field) {
            $this->assertContains($field, $fillable, "Expected '$field' in fillable");
        }
    }
}
