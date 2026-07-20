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
        DB::statement("ALTER TABLE organization_packages MODIFY COLUMN status ENUM('active','pending','exhausted','expired','converted') DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE organization_packages MODIFY COLUMN status ENUM('active','pending','exhausted','expired') DEFAULT 'active'");
    }
};
