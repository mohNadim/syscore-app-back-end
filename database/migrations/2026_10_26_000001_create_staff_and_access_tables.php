<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 100);
            $table->string('name_en', 100)->nullable();
            $table->boolean('is_system')->default(false);
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('description', 255)->nullable();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles');
            $table->foreignId('permission_id')->constrained('permissions');
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->string('username', 100)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password_hash', 255);
            $table->foreignId('role_id')->constrained('roles');
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_login_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value');
            $table->foreignId('updated_by_staff_id')->nullable()->constrained('staff');
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
