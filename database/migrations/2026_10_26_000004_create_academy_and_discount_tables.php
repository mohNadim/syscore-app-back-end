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
        Schema::create('coaches', function (Blueprint $table) {
            $table->id();
            $table->string('full_name_ar', 150);
            $table->string('full_name_en', 150)->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->text('bio_ar')->nullable();
            $table->text('bio_en')->nullable();
            $table->foreignId('sport_id')->nullable()->constrained('sports');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
        });

        Schema::create('academies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained('sports');
            $table->string('name_ar', 150);
            $table->string('name_en', 150)->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('cover_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('academy_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academy_id')->constrained('academies');
            $table->foreignId('coach_id')->nullable()->constrained('coaches');
            $table->string('name_ar', 150);
            $table->string('name_en', 150)->nullable();
            $table->unsignedSmallInteger('age_min');
            $table->unsignedSmallInteger('age_max');
            $table->enum('gender', ['male', 'female', 'mixed']);
            $table->unsignedInteger('capacity');
            $table->string('level', 50)->nullable();
            $table->integer('monthly_price');
            $table->unsignedSmallInteger('subscription_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('group_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('academy_groups');
            $table->foreignId('unit_id')->constrained('bookable_units');
            $table->unsignedSmallInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('academy_groups');
            $table->foreignId('schedule_id')->nullable()->constrained('group_schedules');
            $table->foreignId('unit_id')->constrained('bookable_units');
            $table->foreignId('coach_id')->nullable()->constrained('coaches');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('status', ['scheduled', 'changed', 'cancelled', 'done'])->default('scheduled');
            $table->dateTime('original_starts_at')->nullable();
            $table->string('change_reason', 255)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['group_id', 'starts_at']);
            $table->index(['unit_id', 'starts_at']);
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150);
            $table->string('name_en', 150)->nullable();
            $table->enum('type', ['percent', 'fixed']);
            $table->integer('value');
            $table->enum('target', ['booking', 'subscription', 'all'])->default('all');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();
            $table->unsignedSmallInteger('weekdays')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('notify_users')->default(false);
            $table->foreignId('created_by_staff_id')->nullable()->constrained('staff');
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('discount_categories', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained('discounts');
            $table->foreignId('category_id')->constrained('user_categories');

            $table->primary(['discount_id', 'category_id']);
        });

        Schema::create('discount_sports', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained('discounts');
            $table->foreignId('sport_id')->constrained('sports');

            $table->primary(['discount_id', 'sport_id']);
        });

        Schema::create('discount_academies', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained('discounts');
            $table->foreignId('academy_id')->constrained('academies');

            $table->primary(['discount_id', 'academy_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_academies');
        Schema::dropIfExists('discount_sports');
        Schema::dropIfExists('discount_categories');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('group_schedules');
        Schema::dropIfExists('academy_groups');
        Schema::dropIfExists('academies');
        Schema::dropIfExists('coaches');
    }
};
