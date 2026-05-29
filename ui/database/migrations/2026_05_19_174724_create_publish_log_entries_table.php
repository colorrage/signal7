<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publish_log_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('asset_id');
            $table->string('idempotency_key');
            $table->string('channel')->nullable();
            $table->string('status')->default('published'); // published, skipped-duplicate, blocked-expired, blocked-external-gate, blocked-rate-limit, failed
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique('idempotency_key');
            $table->index('task_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publish_log_entries');
    }
};
