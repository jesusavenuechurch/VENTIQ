<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_invites', function (Blueprint $table) {
            $table->string('role', 32)->default('staff')->after('email');
        });

        // People who joined through an invite were never given a role, so
        // the organizer area let them do nothing. Give them Staff, the
        // role an invite now defaults to.
        $staff = DB::table('roles')->where('name', 'staff')->where('guard_name', 'web')->value('id');
        if (!$staff) {
            return;
        }

        $roleless = DB::table('users')
            ->whereNotNull('organization_id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('model_has_roles')
                ->whereColumn('model_has_roles.model_id', 'users.id')
                ->where('model_has_roles.model_type', \App\Models\User::class))
            ->pluck('id');

        DB::table('model_has_roles')->insert($roleless->map(fn ($id) => [
            'role_id' => $staff, 'model_type' => \App\Models\User::class, 'model_id' => $id,
        ])->all());

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('organization_invites', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
