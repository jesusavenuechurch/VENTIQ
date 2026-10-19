<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('certificates_enabled')->default(false)->after('is_public');
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete(); // the Programme
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique(); // for a public verify link, mirrors how invites/checkins already work
            $table->timestamp('issued_at');
            $table->timestamps();

            // One certificate per person per Programme — issuing again
            // just re-downloads the same one, doesn't duplicate.
            $table->unique(['event_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('certificates_enabled');
        });
    }
};