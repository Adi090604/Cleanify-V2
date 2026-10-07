<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_area_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('area_name');
            $table->string('normalized_area_name');
            $table->string('barangay')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('details')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('service_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['user_id', 'status', 'normalized_area_name'],
                'service_area_requests_pending_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_area_requests');
    }
};
