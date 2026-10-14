<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // When the halfway "please pay" reminder went out, so it's sent once.
            $table->timestamp('payment_reminder_sent_at')->nullable()->after('payment_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('payment_reminder_sent_at');
        });
    }
};
