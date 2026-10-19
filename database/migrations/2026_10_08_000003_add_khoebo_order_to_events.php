<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The event's order in Khoebo: its possible fees, from its ticket numbers.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('khoebo_order_id')->nullable();
            $table->string('khoebo_order_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['khoebo_order_id', 'khoebo_order_reference']);
        });
    }
};
