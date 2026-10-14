<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The SMS VENTIQ's merchant SIM receives, forwarded by the phone that holds
// it: each payment into the merchant wallet, read and matched to a ticket.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_money_messages', function (Blueprint $table) {
            $table->id();
            $table->string('provider');                       // ecocash
            $table->string('sender');                         // 199
            $table->text('body');
            $table->timestamp('received_at');
            $table->string('kind');                           // in, out, other
            $table->string('transaction_id')->nullable();     // the approval code / Txn ID
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('payer_name')->nullable();
            $table->string('payer_phone')->nullable();        // 8 local digits
            $table->string('reference')->nullable();          // what the payer typed
            $table->decimal('balance_after', 12, 2)->nullable();
            // Whether the balance follows on from the message before: false
            // keeps it out of the chain until a person says it's real.
            $table->boolean('balance_ok')->nullable();
            // matched, online, unmatched, held, dismissed, out, ignored
            $table->string('status');
            $table->string('note')->nullable();
            $table->foreignId('payment_session_id')->nullable()->constrained('payment_sessions')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('device')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            // One message per transaction: a forwarder retrying adds nothing.
            $table->unique(['provider', 'transaction_id']);
            $table->index(['status', 'received_at']);
            $table->index('payer_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_money_messages');
    }
};
