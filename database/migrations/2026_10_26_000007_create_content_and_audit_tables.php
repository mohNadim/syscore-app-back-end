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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->enum('audience', ['all', 'academy']);
            $table->foreignId('academy_id')->nullable()->constrained('academies');
            $table->string('title_ar', 200);
            $table->string('title_en', 200)->nullable();
            $table->text('body_ar');
            $table->text('body_en')->nullable();
            $table->foreignId('sent_by_staff_id')->constrained('staff');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('type', 50);
            $table->string('title_ar', 200)->nullable();
            $table->string('title_en', 200)->nullable();
            $table->text('body_ar')->nullable();
            $table->text('body_en')->nullable();
            $table->json('data')->nullable();
            $table->foreignId('announcement_id')->nullable()->constrained('announcements');
            $table->dateTime('push_sent_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('title_ar', 200);
            $table->string('title_en', 200)->nullable();
            $table->text('body_ar')->nullable();
            $table->text('body_en')->nullable();
            $table->string('cover_url', 500)->nullable();
            $table->boolean('is_published')->default(false);
            $table->dateTime('published_at')->nullable();
            $table->foreignId('created_by_staff_id')->nullable()->constrained('staff');
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('gallery_albums', function (Blueprint $table) {
            $table->id();
            $table->string('title_ar', 200);
            $table->string('title_en', 200)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('gallery_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('gallery_albums');
            $table->string('image_url', 500);
            $table->string('caption_ar', 255)->nullable();
            $table->string('caption_en', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('actor_type', ['user', 'staff', 'system']);
            $table->foreignId('actor_user_id')->nullable()->constrained('users');
            $table->foreignId('actor_staff_id')->nullable()->constrained('staff');
            $table->string('action', 100);
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('gallery_photos');
        Schema::dropIfExists('gallery_albums');
        Schema::dropIfExists('news');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('announcements');
    }
};
