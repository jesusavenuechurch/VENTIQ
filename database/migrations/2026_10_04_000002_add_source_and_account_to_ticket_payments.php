<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records who collected each payment (VENTIQ's gateway or the organizer
 * directly), which organizer account it was paid into, the attendee's
 * proof, and when the attendee submitted it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_payments', function (Blueprint $table) {
            $table->foreignId('organization_payment_method_id')->nullable()->after('payment_method')
                ->constrained('organization_payment_methods')->nullOnDelete();
            // 'ventiq_online' | 'organizer_direct'; null until known.
            $table->string('source', 20)->nullable()->after('organization_payment_method_id')->index();
            $table->string('proof_path')->nullable()->after('payment_reference');
            $table->timestamp('submitted_at')->nullable()->after('payment_date');
        });

        // Only gateway activations ever created settlement items, so an
        // approved payment on a ticket with one is VENTIQ-collected and
        // every other approved payment was collected by the organizer.
        DB::table('ticket_payments')
            ->where('status', 'approved')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('settlement_items')
                ->whereColumn('settlement_items.ticket_id', 'ticket_payments.ticket_id'))
            ->update(['source' => 'ventiq_online']);

        DB::table('ticket_payments')
            ->where('status', 'approved')
            ->whereNull('source')
            ->update(['source' => 'organizer_direct']);

        // A pending row with a method filled in is one the attendee has
        // already submitted on the payment screen.
        DB::table('ticket_payments')
            ->where('status', 'pending')
            ->whereNotNull('payment_method')
            ->update(['submitted_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('ticket_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_payment_method_id');
            $table->dropColumn(['source', 'proof_path', 'submitted_at']);
        });
    }
};
