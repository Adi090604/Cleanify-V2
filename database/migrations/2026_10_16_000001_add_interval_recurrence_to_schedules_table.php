<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->date('recurrence_start_date')->nullable()->after('specific_date');
            $table->unsignedSmallInteger('recurrence_interval_days')->nullable()->after('recurrence_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['recurrence_start_date', 'recurrence_interval_days']);
        });
    }
};
