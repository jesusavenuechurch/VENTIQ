<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The organization's customer id in Khoebo, VENTIQ's accounting system.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedBigInteger('khoebo_customer_id')->nullable()->after('keep_account');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('khoebo_customer_id');
        });
    }
};
