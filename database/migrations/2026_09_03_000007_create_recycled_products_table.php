<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recycled_products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->text('description')->nullable();
            $table->string('source_material');
            $table->foreignId('recyclable_material_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('material_quantity', 12, 2)->nullable();
            $table->string('material_unit', 50)->nullable();
            $table->decimal('product_quantity', 12, 2)->nullable();
            $table->string('product_unit', 50)->nullable();
            $table->date('created_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['planned', 'in_production', 'completed', 'displayed', 'archived'])->default('planned');
            $table->string('image_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recycled_products');
    }
};
