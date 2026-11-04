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
        DB::statement("ALTER TABLE bookings MODIFY payment_mode ENUM('online_full', 'deposit_then_cash', 'cash_full', 'cash_only') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('bookings')->where('payment_mode', 'cash_only')->update(['payment_mode' => 'cash_full']);
        DB::statement("ALTER TABLE bookings MODIFY payment_mode ENUM('online_full', 'deposit_then_cash', 'cash_full') NOT NULL");
    }
};
