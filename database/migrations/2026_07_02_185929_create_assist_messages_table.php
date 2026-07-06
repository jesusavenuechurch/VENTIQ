<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assist_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('assist_conversations')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->longText('content')->nullable(); // null while pending
            $table->enum('status', ['completed', 'pending', 'failed'])->default('completed');
            $table->string('job_id')->nullable(); // ties to AiGenerationResult, same as before
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assist_messages');
    }
};
