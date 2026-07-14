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
        Schema::table('session_segments', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable()->after('ended_at');
            $table->unsignedInteger('paused_seconds')->default(0)->after('paused_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('session_segments', function (Blueprint $table) {
            //
        });
    }
};
