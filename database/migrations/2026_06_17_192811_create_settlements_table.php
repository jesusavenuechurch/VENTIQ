<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add settlement_frequency to organizations
        Schema::table('organizations', function (Blueprint $table) {
            $table->enum('settlement_frequency', ['manual', 'weekly', 'monthly', 'post_event'])
                  ->default('manual')
                  ->after('id');
        });

        // 2. Settlements — one batch per org per payout
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('trigger_type', ['manual', 'weekly', 'monthly', 'post_event'])->default('manual');

            // Aggregated money columns — sums of settlement_items
            $table->decimal('gross_paid', 12, 2)->default(0);          // sum of what attendees paid
            $table->decimal('gateway_fees', 12, 2)->default(0);        // sum of MoPay cuts (informational)
            $table->decimal('amount_received', 12, 2)->default(0);     // sum landed in Ventiq
            $table->decimal('amount_owed_to_org', 12, 2)->default(0);  // sum of base ticket prices
            $table->decimal('ventiq_revenue', 12, 2)->default(0);      // amount_received - amount_owed_to_org

            $table->enum('status', ['pending', 'partial', 'settled'])->default('pending');
            $table->string('settlement_method')->nullable();   // mpesa, bank_transfer etc
            $table->string('settlement_reference')->nullable(); // transaction ref when you pay the org
            $table->text('notes')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('status');
        });

        // 3. Settlement items — one row per online ticket payment
        Schema::create('settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Per-ticket economics
            $table->decimal('ticket_amount', 10, 2);       // base tier price
            $table->decimal('gross_paid', 10, 2);          // ticket_amount × (1 + surcharge_rate)
            $table->decimal('gateway_fee', 10, 2);         // gross_paid × gateway_fee_rate
            $table->decimal('amount_received', 10, 2);     // gross_paid - gateway_fee
            $table->decimal('amount_owed_to_org', 10, 2);  // = ticket_amount

            $table->timestamps();

            $table->index('settlement_id');
            $table->index('ticket_id');
            $table->index('payment_session_id');
            $table->index('organization_id');

            // Prevent double-counting the same ticket in two settlements
            $table->unique('payment_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlements');
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('settlement_frequency');
        });
    }
};