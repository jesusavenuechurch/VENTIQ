<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One purchase becomes one ticket with N admissions (a group of 3 is one
 * QR scanned three times), and an unpaid ticket can carry a deadline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedSmallInteger('admissions')->default(1)->after('amount_paid');
            $table->unsignedSmallInteger('admitted_count')->default(0)->after('admissions');
            $table->timestamp('payment_due_at')->nullable()->after('payment_date')->index();
        });

        // Existing tickets each admit one person (companion tickets stay as
        // separate tickets); anything already scanned has used it.
        DB::table('tickets')
            ->whereNotNull('checked_in_at')
            ->update(['admitted_count' => 1]);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['payment_due_at']);
            $table->dropColumn(['admissions', 'admitted_count', 'payment_due_at']);
        });
    }
};
