<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A programme is a course people enrol in once: who's on it (enrolled by
 * its link, by the organizer, or by checking in to one of its days), the
 * link itself, and the rule for a certificate (every day, or at least so
 * many), issued on its own when someone meets it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete(); // the programme
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('institution')->nullable();
            $table->string('position')->nullable();
            // self (the link) | desk (the organizer) | checkin (came to a day)
            $table->string('source', 20)->default('desk');
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'client_id']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('enrol_token', 48)->nullable()->unique()->after('certificates_enabled');
            $table->boolean('enrolment_open')->default(true)->after('enrol_token');
            // Days to attend for a certificate; null is every day.
            $table->unsignedSmallInteger('certificate_min_days')->nullable()->after('enrolment_open');
        });

        // Everyone who already came to a programme's day is on it.
        DB::table('participants')
            ->join('capture_sessions', 'capture_sessions.id', '=', 'participants.session_id')
            ->join('events', 'events.id', '=', 'capture_sessions.event_id')
            ->where('events.is_programme', true)
            ->whereNotNull('participants.attended_at')
            ->select('events.id as event_id', 'events.organization_id', 'participants.client_id', DB::raw('MAX(participants.institution) as institution'), DB::raw('MAX(participants.position) as position'), DB::raw('MIN(participants.attended_at) as created_at'))
            ->groupBy('events.id', 'events.organization_id', 'participants.client_id')
            ->get()
            ->each(fn ($r) => DB::table('programme_enrolments')->insertOrIgnore([
                'organization_id' => $r->organization_id, 'event_id' => $r->event_id, 'client_id' => $r->client_id,
                'institution' => $r->institution, 'position' => $r->position, 'source' => 'checkin',
                'created_at' => $r->created_at, 'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_enrolments');
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['enrol_token']);
            $table->dropColumn(['enrol_token', 'enrolment_open', 'certificate_min_days']);
        });
    }
};
