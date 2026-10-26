<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A pilot (a corporate rate at M0) runs for three months, then ends by
 * itself unless VENTIQ renews it. VENTIQ is told a week before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_agreements', function (Blueprint $table) {
            $table->date('ends_on')->nullable()->after('starts_on');
            $table->timestamp('end_notice_at')->nullable()->after('ends_on');
        });

        // Pilots already running: three months from when they started.
        DB::table('session_agreements')->where('rate', 0)->whereNull('ended_at')
            ->update(['ends_on' => DB::raw("DATE_ADD(starts_on, INTERVAL 3 MONTH)")]);
    }

    public function down(): void
    {
        Schema::table('session_agreements', function (Blueprint $table) {
            $table->dropColumn(['ends_on', 'end_notice_at']);
        });
    }
};
