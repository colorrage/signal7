<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->enum('system', ['signal7', 'hyper7']);
            $table->string('name');
            $table->string('filename');
            $table->string('content_hash')->nullable();
            $table->timestamps();

            $table->unique(['system', 'filename']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
