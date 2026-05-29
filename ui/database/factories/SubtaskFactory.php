<?php

namespace Database\Factories;

use App\Models\Subtask;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subtask>
 */
class SubtaskFactory extends Factory
{
    protected $model = Subtask::class;

    public function definition(): array
    {
        static $position = 0;

        return [
            'task_id' => 1, // callers must supply via forTask() or state()
            'subtask_id' => 'T1.'.fake()->unique()->numberBetween(1, 99),
            'parent_subtask_id' => null,
            'title' => fake()->sentence(4),
            'status' => fake()->randomElement(['todo', 'in-progress', 'done']),
            'awaiting' => null,
            'role' => 'impl',
            'depends' => [],
            'writes' => [],
            'body' => fake()->paragraph(),
            'position' => ++$position,
        ];
    }

    public function todo(): static
    {
        return $this->state(['status' => 'todo']);
    }

    public function done(): static
    {
        return $this->state(['status' => 'done']);
    }

    public function forTask(Task $task): static
    {
        return $this->state(['task_id' => $task->id]);
    }
}
