<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which online methods (EcoCash, M-Pesa, card) an event offers. Null on
 * existing events means every method VENTIQ has switched on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('online_methods')->nullable()->after('enabled_payment_method_ids');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('online_methods');
        });
    }
};
