<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tickets bought together for several people: each has its own ticket and
 * QR, and points at the first one, which carries the price of them all and
 * is the one that's paid. When it's paid the others go out with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('group_ticket_id')->nullable()->after('client_id')->constrained('tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_ticket_id');
        });
    }
};
