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
        Schema::create('session_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Plain strings, not DB enums — valid values enforced in
            // SessionPackage/SessionQuotaService, not the schema.
            $table->string('tier'); // free | payg | team | business | enterprise
            $table->string('status')->default('active'); // active | exhausted | cancelled | expired

            $table->unsignedInteger('sessions_included')->default(0);
            $table->unsignedInteger('sessions_used')->default(0);
            $table->unsignedInteger('whatsapp_included')->default(0);
            $table->unsignedInteger('whatsapp_used')->default(0);
            $table->unsignedInteger('sms_included')->default(0);
            $table->unsignedInteger('sms_used')->default(0);

            $table->decimal('price_paid', 10, 2)->nullable();

            // Null only for 'payg' rows — PAYG credit never expires/resets.
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'tier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_packages');
    }
};
