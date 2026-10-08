<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The event's invoice in Khoebo: what the organizer actually owes, after the event.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('khoebo_invoice_id')->nullable();
            $table->string('khoebo_invoice_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['khoebo_invoice_id', 'khoebo_invoice_reference']);
        });
    }
};
