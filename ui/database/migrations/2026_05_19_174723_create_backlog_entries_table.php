<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backlog_entries', function (Blueprint $table) {
            $table->id();
            $table->enum('system', ['signal7', 'hyper7']);
            $table->string('entry_id');       // B1, B2, ...
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open'); // open, promoted, dropped
            $table->timestamps();

            $table->unique(['system', 'entry_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backlog_entries');
    }
};
