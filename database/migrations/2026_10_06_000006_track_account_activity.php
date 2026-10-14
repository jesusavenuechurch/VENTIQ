<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/*
 | Accounts that never created anything are removed after two months of no
 | activity (accounts:remove-unused). That needs to know when people last
 | used VENTIQ, and whom we've warned. Existing users start the clock now:
 | nobody was tracked before, so nobody is counted as inactive yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_active_at')->nullable()->after('remember_token');
            $table->timestamp('removal_warned_at')->nullable()->after('last_active_at');
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->timestamp('removal_warned_at')->nullable();
            // Set by a super admin: never remove, e.g. one set up for a client who hasn't started.
            $table->boolean('keep_account')->default(false);
        });

        DB::table('users')->update(['last_active_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['last_active_at', 'removal_warned_at']));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn(['removal_warned_at', 'keep_account']));
    }
};
