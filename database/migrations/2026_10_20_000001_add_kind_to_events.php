<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What an event row is for. Most are events people buy tickets for; a
 * session that tracks who comes and a programme make one too, only to hold
 * their attendees. Those are kept out of the organizer's events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('kind', 20)->default('event')->after('organization_id');
            $table->index(['organization_id', 'kind']);
        });

        DB::table('events')->where('is_programme', true)->update(['kind' => 'programme']);

        // Made by a session (it was created for it and has no ticket types).
        DB::table('events')
            ->where('is_programme', false)
            ->whereIn('id', DB::table('capture_sessions')->whereNotNull('event_id')->select('event_id'))
            ->whereNotIn('id', DB::table('event_tiers')->select('event_id'))
            ->update(['kind' => 'session']);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
