<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('academy_groups', function (Blueprint $table) {
            $table->string('level_ar', 50)->nullable()->after('level');
            $table->string('level_en', 50)->nullable()->after('level_ar');
        });

        DB::table('academy_groups')->whereNotNull('level')->update(['level_ar' => DB::raw('level')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('academy_groups', function (Blueprint $table) {
            $table->dropColumn(['level_ar', 'level_en']);
        });
    }
};
