<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// When the organizer was told a ticket type sold out, so they're told once.
// Cleared when they raise the number, so a second sell-out is told too.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_tiers', function (Blueprint $table) {
            $table->timestamp('sold_out_notified_at')->nullable()->after('quantity_sold');
        });
    }

    public function down(): void
    {
        Schema::table('event_tiers', function (Blueprint $table) {
            $table->dropColumn('sold_out_notified_at');
        });
    }
};
