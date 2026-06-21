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
        Schema::create('workshop_ticket_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')
                ->unique() // one detail row per ticket
                ->constrained('tickets')
                ->cascadeOnDelete();
 
            // Attendee professional context — event-scoped, not identity data
            // Same person can attend different workshops in different capacities
            $table->string('position')->nullable()
                ->comment('e.g. Nurse, District Coordinator, Finance Officer');
 
            $table->string('institution')->nullable()
                ->comment('e.g. Ministry of Health, Baylor, District Hospital');
 
            $table->string('district', 30)->nullable()
                ->comment('Governed by config/constants.php workshop_districts');
 
            // ── Signature ─────────────────────────────────────────────────────
            // Captured at gate on shared tablet after check-in
            // Base64 PNG stored as file, path saved here — same pattern as qr_code_path
            $table->string('signature_path')->nullable();
            $table->timestamp('signed_at')->nullable();
 
            // Who on the gate team collected this signature
            $table->foreignId('signed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
 
            // Status governed by config/constants.php signature_statuses
            // pending / signed / declined / skipped
            $table->string('signature_status', 20)
                ->default('pending')
                ->comment('Governed by config/constants.php signature_statuses');
 
            // Device info — useful for audit trail, optional
            $table->string('signed_on_device')->nullable()
                ->comment('Browser user-agent or device name at time of signing');
 
            $table->timestamps();
 
            // Common query: all workshop details for an event
            $table->index(['ticket_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_ticket_details');
    }
};
