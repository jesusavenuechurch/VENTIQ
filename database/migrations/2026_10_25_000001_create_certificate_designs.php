<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificates with a designer: one design per organization, and
 * optionally one for a programme; up to two signatories from the team,
 * each allowed to sign by an Admin and drawing their signature once. An
 * issued certificate keeps the design and signatures it was issued with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_sign_certificates')->default(false);
            // On the private disk, a new file each time it's redrawn.
            $table->string('signature_path')->nullable();
            // Printed under the signature, e.g. "Programme Director".
            $table->string('signature_title')->nullable();
            $table->timestamp('signature_updated_at')->nullable();
        });

        Schema::create('certificate_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // A programme's own design; null is the organization's.
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('layout', 20)->default('banner');
            $table->string('title')->default('Certificate');
            $table->string('subtitle')->nullable();
            $table->text('wording')->nullable();
            $table->string('primary_color', 7)->default('#1D4069');
            $table->string('accent_color', 7)->default('#F07F22');
            $table->boolean('show_logo')->default(true);
            $table->json('signatories')->nullable();   // [user id, user id]
            $table->timestamps();

            $table->unique(['organization_id', 'event_id']);
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->json('design')->nullable()->after('certificate_number');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', fn (Blueprint $table) => $table->dropColumn('design'));
        Schema::dropIfExists('certificate_designs');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['can_sign_certificates', 'signature_path', 'signature_title', 'signature_updated_at']));
    }
};
