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
        // The original migration file was hand-edited to default('paylesotho') after
        // it had already run in some environments, so the live default stayed 'mopay'.
        // Correct the live default here instead of editing migration history.
        //
        // mopay_reference is a MoPay-only leftover (NOT NULL + UNIQUE from the original
        // create_payment_sessions_table migration) that PayLesotho sessions never
        // populate — make it nullable so PayLesotho payment initiation doesn't hard-fail.
        // A unique index still permits multiple NULLs under MySQL, so uniqueness for
        // MoPay rows that do set it is preserved.
        DB::statement("ALTER TABLE payment_sessions MODIFY gateway VARCHAR(255) NOT NULL DEFAULT 'paylesotho'");
        DB::statement("ALTER TABLE payment_sessions MODIFY mopay_reference VARCHAR(255) NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE payment_sessions MODIFY gateway VARCHAR(255) NOT NULL DEFAULT 'mopay'");
        DB::statement("ALTER TABLE payment_sessions MODIFY mopay_reference VARCHAR(255) NOT NULL");
    }
};
