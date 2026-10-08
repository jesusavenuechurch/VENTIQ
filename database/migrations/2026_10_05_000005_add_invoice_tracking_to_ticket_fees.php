<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing organizers for fees VENTIQ didn't collect itself: which invoice
 * a fee went on (the reference from the invoicing system) and when it
 * was paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_fees', function (Blueprint $table) {
            $table->string('invoice_reference', 100)->nullable()->after('invoiced_at')->index();
            $table->timestamp('invoice_paid_at')->nullable()->after('invoice_reference');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_fees', function (Blueprint $table) {
            $table->dropColumn(['invoice_reference', 'invoice_paid_at']);
        });
    }
};
