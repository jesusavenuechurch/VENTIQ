<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An organization can now hold several accounts for the same method (two
 * EcoCash numbers, say), with one marked as the default for new events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_payment_methods', function (Blueprint $table) {
            // The organization_id foreign key currently relies on the
            // unique index (organization_id is its first column), so MySQL
            // refuses to drop it until another index covers the column.
            $table->index('organization_id', 'org_payment_methods_org_idx');
        });

        Schema::table('organization_payment_methods', function (Blueprint $table) {
            $table->dropUnique('org_payment_method_unique');
            $table->boolean('is_default')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('organization_payment_methods', function (Blueprint $table) {
            $table->dropColumn('is_default');
            $table->unique(['organization_id', 'payment_method'], 'org_payment_method_unique');
        });

        Schema::table('organization_payment_methods', function (Blueprint $table) {
            $table->dropIndex('org_payment_methods_org_idx');
        });
    }
};
