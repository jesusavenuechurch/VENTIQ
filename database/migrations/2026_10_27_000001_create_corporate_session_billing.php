<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corporate rates for Sessions: an organization's agreed price per
 * session (set by a super admin), with its free sessions each month.
 * Sessions held are priced when they start and invoiced monthly; the
 * invoice is raised in Khoebo and emailed by VENTIQ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('rate', 10, 2);                         // per session, Maloti
            $table->unsignedSmallInteger('free_per_month')->default(1);
            $table->string('billing_email')->nullable();           // besides the organization's admins
            $table->text('notes')->nullable();
            $table->date('starts_on');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('session_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30)->unique();                 // VQS-2026-0001
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('rate', 10, 2);
            $table->unsignedInteger('sessions_count');
            $table->unsignedInteger('free_count');
            $table->decimal('total', 10, 2);
            $table->date('due_date');
            $table->string('status', 10)->default('unpaid');        // unpaid | paid | void
            $table->unsignedBigInteger('khoebo_invoice_id')->nullable();
            $table->string('khoebo_reference')->nullable();
            $table->text('khoebo_error')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_reference')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'period_start']);
        });

        Schema::table('capture_sessions', function (Blueprint $table) {
            // On a corporate rate: what this session costs (0 = one of the month's free ones), and its invoice.
            $table->decimal('charge', 10, 2)->nullable()->after('counted_at');
            $table->foreignId('session_invoice_id')->nullable()->after('charge')->constrained('session_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('capture_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('session_invoice_id');
            $table->dropColumn('charge');
        });
        Schema::dropIfExists('session_invoices');
        Schema::dropIfExists('session_agreements');
    }
};
