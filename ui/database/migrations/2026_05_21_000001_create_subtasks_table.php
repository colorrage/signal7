<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('subtask_id');            // e.g. T4.7
            $table->string('parent_subtask_id')->nullable(); // sibling id when nested
            $table->string('title');
            $table->string('status')->default('todo'); // todo, in-progress, done
            $table->string('awaiting')->nullable();
            $table->string('role')->nullable();
            $table->json('depends')->nullable();
            $table->json('writes')->nullable();
            $table->longText('body')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->unique(['task_id', 'subtask_id']);
            $table->index('status');
            $table->index(['task_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subtasks');
    }
};
