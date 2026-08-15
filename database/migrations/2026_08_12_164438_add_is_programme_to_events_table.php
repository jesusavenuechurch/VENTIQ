<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_programme')->default(false)->after('organization_package_id');
        });

        // Backfill: whereNull('organization_package_id') — the old Programme
        // definition — turned out to already misclassify real ticketed
        // events in practice (package assignment wasn't populated for them
        // either). A ticketed event always gets at least one EventTier row
        // (CreateEvent::afterCreate() creates one immediately, for both free
        // and paid events); a Programme created via ProgrammeController
        // never creates any. "Has zero tiers" is the accurate historical
        // signal, verified directly against this database's actual rows.
        DB::table('events')
            ->whereNotIn('id', DB::table('event_tiers')->select('event_id')->distinct())
            ->update(['is_programme' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('is_programme');
        });
    }
};
