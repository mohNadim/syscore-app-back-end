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
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['pitch', 'hall']);
            $table->string('name_ar', 100);
            $table->string('name_en', 100)->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities');
            $table->string('code', 20);
            $table->string('name_ar', 100);
            $table->string('name_en', 100)->nullable();
        });

        Schema::create('bookable_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities');
            $table->string('code', 20)->unique();
            $table->string('name_ar', 100);
            $table->string('name_en', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
        });

        Schema::create('unit_spaces', function (Blueprint $table) {
            $table->foreignId('unit_id')->constrained('bookable_units');
            $table->foreignId('space_id')->constrained('spaces');

            $table->primary(['unit_id', 'space_id']);
        });

        Schema::create('sports', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 100);
            $table->string('name_en', 100)->nullable();
            $table->string('icon_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('unit_sports', function (Blueprint $table) {
            $table->foreignId('unit_id')->constrained('bookable_units');
            $table->foreignId('sport_id')->constrained('sports');

            $table->primary(['unit_id', 'sport_id']);
        });

        Schema::create('operating_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities');
            $table->unsignedSmallInteger('weekday');
            $table->time('open_time');
            $table->time('close_time');

            $table->unique(['facility_id', 'weekday']);
        });

        Schema::create('closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained('facilities');
            $table->foreignId('space_id')->nullable()->constrained('spaces');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason_ar', 255)->nullable();
            $table->string('reason_en', 255)->nullable();
            $table->foreignId('created_by_staff_id')->nullable()->constrained('staff');
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('bookable_units');
            $table->unsignedSmallInteger('weekday')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_peak')->default(false);
            $table->integer('price_per_hour');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['unit_id', 'weekday']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_rules');
        Schema::dropIfExists('closures');
        Schema::dropIfExists('operating_hours');
        Schema::dropIfExists('unit_sports');
        Schema::dropIfExists('sports');
        Schema::dropIfExists('unit_spaces');
        Schema::dropIfExists('bookable_units');
        Schema::dropIfExists('spaces');
        Schema::dropIfExists('facilities');
    }
};
