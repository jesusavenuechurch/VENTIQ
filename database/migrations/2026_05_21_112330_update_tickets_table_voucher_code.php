<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Short human-readable code — format VQ-XXXX
            // Unique across all tickets, nullable for existing records
            $table->string('voucher_code', 10)->nullable()->unique()->after('qr_code');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('voucher_code');
        });
    }
};