<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// What the event's Khoebo invoice is for and what's been paid on it; a
// prepaid invoice (raised before the event) can be followed by a balance.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->decimal('khoebo_invoice_total', 12, 2)->nullable();
            $table->boolean('khoebo_invoice_prepaid')->default(false);
            $table->decimal('khoebo_paid_total', 12, 2)->default(0);
            $table->unsignedBigInteger('khoebo_balance_invoice_id')->nullable();
            $table->string('khoebo_balance_invoice_reference')->nullable();
            $table->decimal('khoebo_balance_total', 12, 2)->nullable();
            $table->decimal('khoebo_balance_paid', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['khoebo_invoice_total', 'khoebo_invoice_prepaid', 'khoebo_paid_total',
                'khoebo_balance_invoice_id', 'khoebo_balance_invoice_reference', 'khoebo_balance_total', 'khoebo_balance_paid']);
        });
    }
};
