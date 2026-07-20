<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            // Optional — set only when this participant is also a presenter
            // with their own AI-captured segment, so the two never have to
            // duplicate identity.
            $table->foreignId('session_segment_id')->nullable()->constrained('session_segments')->nullOnDelete();

            // Optional — set only when a formal Ticket also exists for this
            // person (paid/pre-registered). Null for a plain walk-in.
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();

            $table->string('role')->default('attendee'); // attendee | presenter | judge | staff | speaker
            $table->string('source')->default('walk_in'); // walk_in | pre_registered | manual

            $table->timestamp('attended_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'role']);
            $table->unique(['event_id', 'client_id']); // one participant row per person per event
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};