<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paid plans renew by being paid: paid_until is how far the plan is paid,
 * and a month's payment moves it on a month. A plan whose new month isn't
 * paid runs for a few days' grace, then goes back to Free. The two
 * notices remember which month they were sent for, so each goes once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_packages', function (Blueprint $table) {
            $table->timestamp('paid_until')->nullable()->after('period_end');
            $table->date('due_notice_for')->nullable()->after('paid_until');
            $table->date('grace_notice_for')->nullable()->after('due_notice_for');
        });

        // Plans bought before this: paid to the end of the month they're in.
        DB::table('session_packages')->where('status', 'active')->whereIn('tier', ['team', 'business'])->where('price_paid', '>', 0)
            ->update(['paid_until' => DB::raw('period_end')]);
    }

    public function down(): void
    {
        Schema::table('session_packages', function (Blueprint $table) {
            $table->dropColumn(['paid_until', 'due_notice_for', 'grace_notice_for']);
        });
    }
};
