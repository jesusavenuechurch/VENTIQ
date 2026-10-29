<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A session recorded on a phone arrives in 30-second pieces; each is
// written down by Whisper on the Mac mini. The agenda item's transcript
// is its pieces' text, in order, until someone edits it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_recording_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('capture_sessions')->cascadeOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('session_segments')->nullOnDelete();
            $table->unsignedInteger('seq');
            $table->string('recorder', 64);
            $table->timestamp('recorded_at')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('path')->nullable();
            $table->string('mime', 60)->nullable();
            // pending → done (text), empty (nothing said), failed
            $table->string('status', 12)->default('pending');
            $table->longText('text')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'seq']);
            $table->index(['session_id', 'status']);
        });

        Schema::table('session_segments', function (Blueprint $table) {
            $table->longText('transcript')->nullable()->after('raw_log');
            $table->timestamp('transcript_edited_at')->nullable()->after('transcript');
        });
    }

    public function down(): void
    {
        Schema::table('session_segments', function (Blueprint $table) {
            $table->dropColumn(['transcript', 'transcript_edited_at']);
        });
        Schema::dropIfExists('session_recording_pieces');
    }
};
