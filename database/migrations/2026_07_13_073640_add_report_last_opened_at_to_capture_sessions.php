<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capture_sessions', function (Blueprint $table) {
            // V1 assumes one operator per organization — this is a fact
            // about the session, not a per-user state. The moment a real
            // multi-staff org needs "has Mary seen it" separately from
            // "has John seen it," this column gets replaced by a
            // session_report_views table (user_id, session_id, viewed_at).
            // Until then, this is the whole feature.
            $table->timestamp('report_last_opened_at')->nullable()->after('session_report');
        });
    }

    public function down(): void
    {
        Schema::table('capture_sessions', function (Blueprint $table) {
            $table->dropColumn('report_last_opened_at');
        });
    }
};