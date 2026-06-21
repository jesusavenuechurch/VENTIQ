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
        Schema::table('organization_packages', function (Blueprint $table) {
            $table->json('features')->nullable()->after('notes');
            $table->integer('max_scanners')->default(1)->after('features');
            $table->integer('max_users')->default(1)->after('max_scanners');
            $table->integer('scanners_used')->default(0)->after('max_scanners');
        });

        // Also fix the enum to include professional and enterprise
        DB::statement("ALTER TABLE organization_packages MODIFY COLUMN package_type ENUM('starter','standard','professional','enterprise','free_trial')");
    }

    public function down(): void
    {
        Schema::table('organization_packages', function (Blueprint $table) {
            $table->dropColumn(['features', 'max_scanners', 'max_users', 'scanners_used']);
        });

        DB::statement("ALTER TABLE organization_packages MODIFY COLUMN package_type ENUM('starter','standard','multi_event','free_trial')");
    }
};
