<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_reports', function (Blueprint $table) {
            $table->unique(['reporter_id', 'report_id']);
        });

        Schema::table('user_reports', function (Blueprint $table) {
            $table->dropUnique('user_reports_reporter_id_reported_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_reports', function (Blueprint $table) {
            $table->unique(['reporter_id', 'reported_user_id']);
        });

        Schema::table('user_reports', function (Blueprint $table) {
            $table->dropUnique('user_reports_reporter_id_report_id_unique');
        });
    }
};
