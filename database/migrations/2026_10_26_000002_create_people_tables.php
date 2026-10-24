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
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('code_hash', 255);
            $table->enum('channel', ['sms', 'whatsapp', 'telegram']);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('consumed_at')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['phone', 'created_at']);
        });

        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('platform', ['android', 'ios']);
            $table->string('push_token', 500)->unique();
            $table->string('app_version', 20)->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('full_name', 150);
            $table->date('birth_date');
            $table->enum('gender', ['male', 'female']);
            $table->text('health_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('user_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 100);
            $table->string('name_en', 100)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('user_category_members', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('category_id')->constrained('user_categories');
            $table->foreignId('assigned_by_staff_id')->nullable()->constrained('staff');
            $table->dateTime('assigned_at')->useCurrent();

            $table->primary(['user_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_category_members');
        Schema::dropIfExists('user_categories');
        Schema::dropIfExists('children');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('otp_codes');
    }
};
