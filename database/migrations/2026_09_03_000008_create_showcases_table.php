<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showcases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recycled_product_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('purpose');
            $table->string('location');
            $table->text('description')->nullable();
            $table->date('displayed_date')->nullable();
            $table->string('contact')->nullable();
            $table->enum('status', ['planned', 'displayed', 'archived'])->default('planned');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'displayed_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showcases');
    }
};
