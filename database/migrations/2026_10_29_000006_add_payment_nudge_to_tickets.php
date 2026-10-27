<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Someone who registered and left without starting to pay gets the
 * payment reminder about 15 minutes later (with the pay button), as well
 * as the one halfway through their time to pay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('payment_nudge_sent_at')->nullable()->after('payment_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('payment_nudge_sent_at');
        });
    }
};
