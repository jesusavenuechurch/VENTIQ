<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the legacy "partners" check-in system (the pre-Ventiq
 * Angel Lounge partner-management layer). Nothing in the current
 * ticketing / programme / scanner code touches these tables — see the
 * matching removal of the Partner/CheckIn models, controllers, Filament
 * resource and widgets in the same change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('check_ins'); // has an FK onto partners — drop first
        Schema::dropIfExists('partners');
    }

    public function down(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('designation')->nullable();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('region')->nullable();
            $table->string('zone')->nullable();
            $table->string('group')->nullable();
            $table->string('church')->nullable();
            $table->string('tier', 100)->default('ruby');
            $table->integer('ror_copies_sponsored')->nullable();
            $table->boolean('will_attend_ippc')->default(false);
            $table->boolean('will_be_at_exhibition')->nullable();
            $table->text('delivery_method')->nullable();
            $table->boolean('coming_with_spouse')->default(false);
            $table->string('spouse_title')->nullable();
            $table->string('spouse_name')->nullable();
            $table->string('spouse_kc_handle')->nullable();
            $table->string('registration_token', 64)->nullable();
            $table->string('verification_token', 64)->nullable();
            $table->string('qr_code_path')->nullable();
            $table->boolean('is_registered')->default(false);
            $table->timestamp('token_used_at')->nullable();
            $table->boolean('email_pending')->default(false);
            $table->boolean('email_sent')->default(false);
            $table->boolean('email_failed')->default(false);
            $table->text('email_response')->nullable();
            $table->timestamps();
        });

        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->onDelete('cascade');
            $table->timestamp('checked_in_at');
            $table->timestamps();
            $table->index(['partner_id', 'checked_in_at']);
        });
    }
};
