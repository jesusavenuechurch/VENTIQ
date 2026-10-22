<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repeating sessions: a series ("Weekly ops meeting, Mondays 9:00") makes
 * each of its meetings ahead of time. And a session counts against the
 * plan when it's held (counted_at), not when it's scheduled, so a series
 * can schedule ahead without using the month up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('type')->default('meeting');
            $table->string('custom_type_label')->nullable();
            $table->time('start_time')->nullable();
            $table->unsignedInteger('expected_duration')->nullable();
            $table->boolean('track_participants')->default(false);
            $table->unsignedInteger('expected_participants')->nullable();
            // [{name, role, presenting}] for each meeting.
            $table->json('people')->nullable();
            // weekly | fortnightly | monthly_date (same day of the month) |
            // monthly_weekday (e.g. the 2nd Tuesday)
            $table->string('frequency', 20);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            // Meetings are made up to this date; later ones come with the top-up.
            $table->date('scheduled_until')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'ended_at']);
        });

        Schema::table('capture_sessions', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->after('parent_session_id')->constrained('session_series')->nullOnDelete();
            $table->timestamp('counted_at')->nullable()->after('status');
        });

        // Every session so far was counted when it was made.
        DB::table('capture_sessions')->update(['counted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('capture_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('series_id');
            $table->dropColumn('counted_at');
        });
        Schema::dropIfExists('session_series');
    }
};
