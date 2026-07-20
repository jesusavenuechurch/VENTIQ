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
        Schema::create('ai_generation_results', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique()->index(); // links Livewire to the job
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('event_description'); // for future job types
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->longText('payload')->nullable();  // the prompt input
            $table->longText('result')->nullable();   // raw AI output
            $table->json('sections')->nullable();     // parsed sections
            $table->string('error')->nullable();
            $table->float('duration')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generation_results');
    }
};
