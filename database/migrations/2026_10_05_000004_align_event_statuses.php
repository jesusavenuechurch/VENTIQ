<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Log};

/**
 * The events.status column allowed draft, published, live and closed, but
 * the app (config constants.event_statuses, Filament and the organizer
 * form) uses draft, published, ongoing, completed and cancelled. Saving
 * one of the last three failed on strict MySQL, or stored an empty status
 * otherwise.
 *
 * live and closed (never set by the app) become ongoing and completed. An
 * empty status was someone choosing ongoing, completed or cancelled: it
 * becomes completed for past events and cancelled for upcoming ones.
 */
return new class extends Migration
{
    private const FINAL = "'draft','published','ongoing','completed','cancelled'";

    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE events MODIFY status ENUM('','draft','published','live','closed','ongoing','completed','cancelled') NOT NULL DEFAULT 'draft'");

        $mapped = [
            'live'   => DB::table('events')->where('status', 'live')->update(['status' => 'ongoing']),
            'closed' => DB::table('events')->where('status', 'closed')->update(['status' => 'completed']),
            'empty, past'     => DB::table('events')->where('status', '')->where('event_date', '<', now())->update(['status' => 'completed']),
            'empty, upcoming' => DB::table('events')->where('status', '')->update(['status' => 'cancelled']),
        ];
        Log::info('Event statuses aligned', $mapped);

        DB::statement('ALTER TABLE events MODIFY status ENUM(' . self::FINAL . ") NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE events MODIFY status ENUM('draft','published','live','closed','ongoing','completed','cancelled') NOT NULL DEFAULT 'draft'");
    }
};
