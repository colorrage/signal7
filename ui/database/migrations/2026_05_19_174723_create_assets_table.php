<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('asset_id');       // A1, A2, ...
            $table->string('title');
            $table->string('asset_type');     // social-copy, email-copy, blog, landing-page, copy, image-prompt, video-script, translation, research, pricing
            $table->string('channel')->nullable();
            $table->string('language')->nullable();
            $table->string('status');         // todo, in-progress, done, needs-revision, blocked, cancelled
            $table->integer('revision')->default(0);
            $table->json('depends')->nullable();
            $table->string('external_gate')->nullable();
            $table->timestamp('publish_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'asset_id']);
            $table->index('status');
            $table->index('asset_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
