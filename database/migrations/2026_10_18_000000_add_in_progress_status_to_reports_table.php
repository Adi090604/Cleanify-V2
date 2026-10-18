<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE reports MODIFY COLUMN status ENUM('pending', 'in_progress', 'resolved', 'rejected') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        DB::table('reports')->where('status', 'in_progress')->update(['status' => 'pending']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE reports MODIFY COLUMN status ENUM('pending', 'resolved', 'rejected') NOT NULL DEFAULT 'pending'");
        }
    }
};
