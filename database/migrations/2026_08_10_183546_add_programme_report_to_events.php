<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->longText('programme_report')->nullable()->after('certificates_enabled');
            $table->string('programme_report_job_id')->nullable()->after('programme_report');
            $table->timestamp('programme_report_generated_at')->nullable()->after('programme_report_job_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['programme_report', 'programme_report_job_id', 'programme_report_generated_at']);
        });
    }
};