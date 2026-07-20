<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settlement_items', function (Blueprint $table) {
            // Makes the foreign key nullable
            $table->foreignId('settlement_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settlement_items', function (Blueprint $table) {
            $table->foreignId('settlement_id')->nullable(false)->change();
        });
    }
};