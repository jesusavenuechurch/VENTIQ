<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Hours an unpaid ticket holds its place. Null = the platform
            // default (config ventiq.payment_window_hours).
            $table->unsignedSmallInteger('payment_window_hours')->nullable()->after('enabled_payment_method_ids');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('payment_window_hours');
        });
    }
};
