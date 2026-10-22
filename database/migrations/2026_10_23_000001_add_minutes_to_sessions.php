<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minutes for meetings: what's been changed by hand on a meeting's
 * minutes (the rest is assembled from its notes, see MinutesBuilder), and
 * a series' chair, secretary and venue for each of its meetings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capture_sessions', function (Blueprint $table) {
            // {present, apologies, venue, aob, next, items: {segmentId: {discussion, decisions}}}
            $table->json('minutes')->nullable()->after('session_report');
        });

        Schema::table('session_series', function (Blueprint $table) {
            // {chair, secretary, location}
            $table->json('meta')->nullable()->after('people');
        });
    }

    public function down(): void
    {
        Schema::table('capture_sessions', fn (Blueprint $table) => $table->dropColumn('minutes'));
        Schema::table('session_series', fn (Blueprint $table) => $table->dropColumn('meta'));
    }
};
