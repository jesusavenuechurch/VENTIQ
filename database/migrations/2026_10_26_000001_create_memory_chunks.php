<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An organization's memory, for Ask Ventiq: its meetings' notes, minutes
 * and action points cut into short passages, each with an embedding made
 * on the Mac mini and kept here, next to the record it points to. The
 * model holds nothing; each question gets the passages that match it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('capture_sessions')->cascadeOnDelete();
            // segment | notes | minutes | action | report
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('title');          // "Weekly ops · 20 Oct 2026 · Logistics"
            $table->text('body');
            $table->date('date')->nullable();
            $table->char('content_hash', 40); // the embedding is redone only when this changes
            $table->binary('embedding')->nullable();   // float32s, normalised
            $table->string('embedding_model', 80)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'date']);
            $table->index(['organization_id', 'embedding_model']);
            $table->fullText(['title', 'body']);
        });

        Schema::table('assist_messages', function (Blueprint $table) {
            // The meetings an answer came from: [{n, title, date, url}].
            $table->json('sources')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('assist_messages', fn (Blueprint $table) => $table->dropColumn('sources'));
        Schema::dropIfExists('memory_chunks');
    }
};
