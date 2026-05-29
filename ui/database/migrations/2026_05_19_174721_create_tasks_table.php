<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->enum('system', ['signal7', 'hyper7']);
            $table->string('task_id');       // S1, T2, ...
            $table->string('folder_name');    // S1-quick-linkedin-launch
            $table->string('title');
            $table->string('phase');          // brief, plan, implement, ...
            $table->string('scope');          // quick, campaign, feature, ...
            $table->boolean('is_archived')->default(false);
            $table->string('awaiting')->nullable();
            $table->timestamp('created_at_disk')->nullable();
            $table->text('dashboard_summary')->nullable();
            $table->string('compliance_status')->nullable(); // clear, questions-open, blocked
            $table->timestamps();

            $table->unique(['system', 'task_id']);
            $table->index('phase');
            $table->index('scope');
            $table->index('is_archived');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
