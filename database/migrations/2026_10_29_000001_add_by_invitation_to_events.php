<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * By invitation: the event's page is up, but nobody registers on it. The
 * organizer issues the tickets (guest list, complimentary, cash) and they
 * go out on WhatsApp as usual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('by_invitation')->default(false)->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('by_invitation');
        });
    }
};
