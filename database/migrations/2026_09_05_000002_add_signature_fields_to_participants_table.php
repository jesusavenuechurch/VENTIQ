<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('district')->nullable()->after('position');
            $table->string('signature_path')->nullable()->after('district');
            $table->timestamp('signed_at')->nullable()->after('signature_path');
            $table->foreignId('signed_by')->nullable()->after('signed_at')->constrained('users')->nullOnDelete();
            $table->string('signature_status')->default('pending')->after('signed_by');
            $table->string('signed_on_device')->nullable()->after('signature_status');
        });

        // The unique constraint was still (event_id, client_id) even after
        // session_id was introduced — PublicSessionCheckinController's
        // firstOrNew(['session_id' => ..., 'client_id' => ...]) assumes
        // session-scoped uniqueness, but the DB never actually enforced
        // that: a client checking into Day 2 of the same Programme (same
        // event_id, different session_id) would fail the old constraint
        // on insert. Fixing this here since the new scanner check-in path
        // reuses this exact insert logic.
        Schema::table('participants', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'client_id']);
            $table->unique(['session_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropUnique(['session_id', 'client_id']);
            $table->unique(['event_id', 'client_id']);
            $table->dropConstrainedForeignId('signed_by');
            $table->dropColumn(['district', 'signature_path', 'signed_at', 'signature_status', 'signed_on_device']);
        });
    }
};
