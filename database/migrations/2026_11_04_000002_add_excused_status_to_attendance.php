<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE attendance MODIFY status ENUM('present', 'absent', 'excused') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('attendance')->where('status', 'excused')->update(['status' => 'absent']);
        DB::statement("ALTER TABLE attendance MODIFY status ENUM('present', 'absent') NOT NULL");
    }
};
