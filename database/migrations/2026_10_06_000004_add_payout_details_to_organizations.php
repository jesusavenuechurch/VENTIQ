<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where VENTIQ pays an organization its online ticket money (net of
 * fees). Kept with who last changed it and when, because a changed
 * payout account is checked before VENTIQ pays into it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('payout_method', 30)->nullable()->after('phone');   // ecocash | mpesa | bank_transfer
            $table->string('payout_account_name')->nullable()->after('payout_method');
            $table->string('payout_account_number', 60)->nullable()->after('payout_account_name');
            $table->string('payout_bank_name')->nullable()->after('payout_account_number');
            $table->timestamp('payout_updated_at')->nullable()->after('payout_bank_name');
            $table->foreignId('payout_updated_by')->nullable()->after('payout_updated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_updated_by');
            $table->dropColumn(['payout_method', 'payout_account_name', 'payout_account_number', 'payout_bank_name', 'payout_updated_at']);
        });
    }
};
