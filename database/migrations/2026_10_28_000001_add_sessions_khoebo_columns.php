<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Sessions books are a company of their own in Khoebo: an
 * organization is a customer there too (its own id), and each Sessions
 * payment keeps the invoice and payment it became there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedBigInteger('sessions_khoebo_customer_id')->nullable()->after('khoebo_customer_id');
        });
        Schema::table('payment_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('khoebo_invoice_id')->nullable();
            $table->string('khoebo_reference')->nullable();
            $table->unsignedBigInteger('khoebo_payment_id')->nullable();
            $table->text('khoebo_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('sessions_khoebo_customer_id'));
        Schema::table('payment_sessions', fn (Blueprint $table) => $table->dropColumn(['khoebo_invoice_id', 'khoebo_reference', 'khoebo_payment_id', 'khoebo_error']));
    }
};
