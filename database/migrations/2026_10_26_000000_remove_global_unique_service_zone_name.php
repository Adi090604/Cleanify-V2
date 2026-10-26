<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_zones', function (Blueprint $table) {
            $table->dropUnique('service_zones_name_unique');
            $table->index('name', 'service_zones_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('service_zones', function (Blueprint $table) {
            $table->dropIndex('service_zones_name_index');
            $table->unique('name', 'service_zones_name_unique');
        });
    }
};
