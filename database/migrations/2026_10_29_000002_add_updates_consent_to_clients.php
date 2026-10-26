<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An attendee agreed, at registration, to hear about the organizer's
 * future events: when, and at which event. Only these people may later
 * be sent invitations in bulk (WhatsApp's rules, and Lesotho's data law).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->timestamp('updates_consent_at')->nullable()->after('status');
            $table->foreignId('updates_consent_event_id')->nullable()->after('updates_consent_at')->constrained('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updates_consent_event_id');
            $table->dropColumn('updates_consent_at');
        });
    }
};
