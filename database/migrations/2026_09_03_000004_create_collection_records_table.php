<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_zone_id')->constrained()->restrictOnDelete();
            $table->foreignId('truck_id')->constrained()->restrictOnDelete();
            $table->date('collection_date');
            $table->foreignId('collected_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['completed', 'cancelled'])->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['collection_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_records');
    }
};
