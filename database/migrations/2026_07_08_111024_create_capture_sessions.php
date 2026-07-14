<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capture_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();

            // Self-reference left unconstrained on purpose — adding a FK to
            // a table's own id from within its own create statement is
            // awkward across DB drivers, and it's not worth the friction
            // for a nullable "previous session" pointer.
            $table->unsignedBigInteger('parent_session_id')->nullable();

            $table->string('type')->default('meeting');
            $table->string('title')->nullable();
            $table->date('date')->nullable();
            $table->string('location')->nullable();
            $table->json('meta')->nullable();
            $table->text('session_report')->nullable();

            // Plain string, not an enum — validated in the model/service
            // layer instead of at the DB level, specifically so adding a
            // new status value later never requires an ALTER TABLE.
            $table->string('status')->default('draft'); // draft | active | completed | reported

            $table->string('report_job_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'created_at']);
            $table->index(['event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capture_sessions');
    }
};