<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_reports', function (Blueprint $table) {
            $table->foreignId('report_id')
                ->nullable()
                ->after('reported_user_id')
                ->constrained('reports')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('report_id');
        });
    }
};
