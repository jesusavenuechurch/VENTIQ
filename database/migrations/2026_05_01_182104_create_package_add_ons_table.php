<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('package_add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_package_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key'); // e.g. 'bulk_upload', 'private_events'
            $table->decimal('price_paid', 10, 2)->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_package_id', 'feature_key']);
            $table->index('organization_package_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_add_ons');
    }
};
