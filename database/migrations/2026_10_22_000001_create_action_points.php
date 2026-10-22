<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Action points: what someone agreed to do in a session, who, by when. One
 * record from the meeting it was agreed in until the one it's done in, so
 * a series' next meeting picks up what's still open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('capture_sessions')->cascadeOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('session_segments')->nullOnDelete();
            $table->foreignId('series_id')->nullable()->constrained('session_series')->nullOnDelete();

            $table->text('task');
            // Who owns it: someone on the team, an attendee, or just a name.
            $table->string('owner_name')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->date('due_date')->nullable();

            // proposed (by the AI, not confirmed yet) | open | done | dropped
            $table->string('status', 20)->default('open');
            $table->string('source', 10)->default('manual'); // ai | manual
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_in_session_id')->nullable()->constrained('capture_sessions')->nullOnDelete();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['series_id', 'status']);
            $table->index(['owner_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_points');
    }
};
