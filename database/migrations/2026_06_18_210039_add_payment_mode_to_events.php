<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->enum('payment_mode', ['free', 'paid'])->default('free')->after('event_type');
            $table->json('enabled_payment_method_ids')->nullable()->after('payment_mode');
            // null = all active org methods (backward compat for existing events)
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['payment_mode', 'enabled_payment_method_ids']);
        });
    }
};