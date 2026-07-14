<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── segment_insights first — the others reference it via extraction_id ──
        Schema::create('segment_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')->constrained('session_segments')->cascadeOnDelete();

            // Plain string, not enum — deliberately, so the two new
            // live-tagging categories (theme, question) sitting alongside
            // the original five never risk the truncation failure we just
            // hit on `status`. Full working set right now:
            // agenda | discussion_point | decision | action_item | open_issue | theme | question
            $table->string('category');

            $table->text('content');
            $table->boolean('is_ai_generated')->default(true);
            $table->unsignedTinyInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['segment_id', 'category']);
        });

        // ── segment_action_items ── action_item-category insights graduate here
        Schema::create('segment_action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')->constrained('session_segments')->cascadeOnDelete();
            $table->foreignId('extraction_id')->nullable()->constrained('segment_insights')->nullOnDelete();

            $table->text('description');
            $table->string('assigned_to_name')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();

            $table->string('status')->default('pending'); // pending | in_progress | completed | carried_forward

            // Self-reference left unconstrained (same reasoning as
            // parent_session_id) — item-level carry-forward chain,
            // "this action first appeared in an earlier segment."
            $table->unsignedBigInteger('carried_from_id')->nullable();

            $table->timestamps();

            $table->index(['segment_id', 'status']);
            $table->index(['assigned_to_user_id', 'status']);
        });

        // ── segment_open_issues ── open_issue-category insights graduate here
        Schema::create('segment_open_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')->constrained('session_segments')->cascadeOnDelete();
            $table->foreignId('extraction_id')->nullable()->constrained('segment_insights')->nullOnDelete();

            $table->text('description');
            $table->string('raised_by')->nullable();
            $table->string('status')->default('open'); // open | resolved | carried_forward

            $table->unsignedBigInteger('carried_from_id')->nullable();

            // Which segment finally resolved this — segment-level now,
            // instead of the original record-level resolved_in_record_id.
            $table->foreignId('resolved_in_segment_id')->nullable()->constrained('session_segments')->nullOnDelete();

            $table->timestamps();

            $table->index(['segment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segment_open_issues');
        Schema::dropIfExists('segment_action_items');
        Schema::dropIfExists('segment_insights');
    }
};