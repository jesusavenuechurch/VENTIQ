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
        Schema::table('capture_sessions', function (Blueprint $table) {
            $table->string('session_code', 8)->nullable()->unique()->after('public_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capture_sessions', function (Blueprint $table) {
            $table->dropColumn('session_code');
        });
    }
};
