<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_sessions', function (Blueprint $table) {
            $table->id();

            // What is being paid for
            $table->string('payable_type'); // 'package' or 'ticket'
            $table->unsignedBigInteger('payable_id'); // OrganizationPackage->id or Ticket->id

            // MoPay session data
            $table->string('mopay_reference')->unique(); // what we sent to MoPay (alphanumeric)
            $table->string('mopay_session_id')->nullable(); // returned by MoPay after session creation
            $table->string('mopay_payment_url')->nullable();

            // Amount
            $table->decimal('amount', 10, 2);

            // Status tracking
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled', 'expired'])->default('pending');
            $table->string('payment_method')->nullable(); // mpesa, ecocash, card — filled on callback
            $table->string('transaction_id')->nullable(); // MoPay's transaction ID on success

            // Context
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('initiated_by')->nullable(); // user id for packages, null for public tickets

            // Raw callback for debugging
            $table->json('callback_payload')->nullable();

            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index('mopay_reference');
            $table->index('mopay_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_sessions');
    }
};