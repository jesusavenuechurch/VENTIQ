<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VENTIQ's fees on every ticket, whoever collected the money.
 *
 *  - service fee: a percentage of the ticket price
 *  - operational fee: a flat amount per person admitted
 *
 * Online money has its fee deducted before payout; money paid directly
 * to the organizer, free tickets and complimentary tickets are invoiced
 * to the organizer. A fee VENTIQ decides not to charge is still recorded,
 * marked as sponsored, so the books show what was given away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // How the ticket was paid for: ventiq_online | organizer_direct | free | complimentary
            $table->string('source', 20)->index();
            $table->decimal('ticket_amount', 10, 2);
            $table->unsignedSmallInteger('people')->default(1);
            $table->decimal('service_fee', 10, 2);
            $table->decimal('operational_fee', 10, 2);
            $table->decimal('total_fee', 10, 2);
            // Waived by VENTIQ for a sponsored event: recorded, not collected.
            $table->boolean('sponsored')->default(false)->index();
            // Deducted from payout (online) or set when invoiced (others).
            $table->string('collection', 20)->index(); // deduct_from_payout | invoice
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            // Not the same as is_sponsored (a promoted listing): this means
            // VENTIQ waives its fees on the event.
            $table->boolean('fees_sponsored')->default(false)->after('is_sponsored');
            $table->timestamp('fees_sponsored_at')->nullable()->after('fees_sponsored');
            $table->foreignId('fees_sponsored_by')->nullable()->after('fees_sponsored_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fees_sponsored_by');
            $table->dropColumn(['fees_sponsored', 'fees_sponsored_at']);
        });

        Schema::dropIfExists('ticket_fees');
    }
};
