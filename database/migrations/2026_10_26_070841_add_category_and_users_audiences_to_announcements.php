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
        DB::statement("ALTER TABLE announcements MODIFY audience ENUM('all','academy','category','users') NOT NULL");

        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('user_category_id')->nullable()->after('academy_id')->constrained('user_categories');
        });

        Schema::create('announcement_recipients', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained('announcements');
            $table->foreignId('user_id')->constrained('users');

            $table->primary(['announcement_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_recipients');

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_category_id');
        });

        DB::table('announcements')->whereIn('audience', ['category', 'users'])->delete();

        DB::statement("ALTER TABLE announcements MODIFY audience ENUM('all','academy') NOT NULL");
    }
};
