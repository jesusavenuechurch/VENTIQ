<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('capture_sessions')->cascadeOnDelete();

            $table->string('presenter_name')->nullable();
            $table->string('title')->nullable(); // the editable "topic" line in the header
            $table->unsignedInteger('order')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('status')->default('upcoming'); // upcoming | active | completed

            $table->json('raw_log')->nullable();    // [{time, text}, ...] — committed lines as typed
            $table->json('ai_summary')->nullable();  // strengths, weaknesses, topics, questions, confidence

            $table->string('extraction_job_id')->nullable(); // reserved for a heavier per-segment report later

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_segments');
    }
};