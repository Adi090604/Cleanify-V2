<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recyclable_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waste_segregation_id')->constrained()->restrictOnDelete();
            $table->string('material');
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 50);
            $table->string('source_location')->nullable();
            $table->date('collection_date');
            $table->enum('status', ['available', 'reserved', 'used', 'processed'])->default('available');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'collection_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recyclable_materials');
    }
};
