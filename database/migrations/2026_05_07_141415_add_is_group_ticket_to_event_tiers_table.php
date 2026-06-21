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
        Schema::table('event_tiers', function (Blueprint $table) {
            $table->boolean('is_group_ticket')->default(false)->after('quantity_per_purchase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_tiers', function (Blueprint $table) {
             $table->dropColumn('is_group_ticket');
        });
    }
};
