<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Khoebo's id for each thing VENTIQ sells (config services.khoebo.products).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('khoebo_products', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedBigInteger('khoebo_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('khoebo_products');
    }
};
