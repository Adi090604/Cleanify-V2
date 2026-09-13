<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_segregations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_record_id')->constrained()->restrictOnDelete();
            $table->enum('category', ['biodegradable', 'recyclable', 'residual', 'special_hazardous']);
            $table->string('material')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 50);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_segregations');
    }
};
