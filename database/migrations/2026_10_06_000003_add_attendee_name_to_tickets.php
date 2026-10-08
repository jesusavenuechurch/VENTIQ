<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The name on a ticket, when it differs from its contact's: two people
 * registering with one phone (a parent for a child, friends sharing a
 * number) each keep their own name on their own pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('attendee_name')->nullable()->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('attendee_name');
        });
    }
};
