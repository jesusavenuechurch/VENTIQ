<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_sessions', function (Blueprint $table) {
            // Holds "what's being bought" between initiate and callback for
            // payable types with no pre-existing row to point payable_id at
            // (e.g. session_package — unlike tickets, there's no pending
            // SessionPackage row to reference; its status list deliberately
            // has no pending placeholder). Ticket payments never set this.
            $table->json('purchase_meta')->nullable()->after('client_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_sessions', function (Blueprint $table) {
            $table->dropColumn('purchase_meta');
        });
    }
};
