<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Double-booking is prevented in application code: reservations are inserted
     * inside a transaction that locks the affected rows in `spaces` (SELECT ... FOR UPDATE)
     * and checks for overlapping active rows in `space_reservations` first.
     */
    public function up(): void
    {
        Schema::create('booking_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('guest_name', 150)->nullable();
            $table->string('guest_phone', 20)->nullable();
            $table->foreignId('unit_id')->constrained('bookable_units');
            $table->foreignId('sport_id')->nullable()->constrained('sports');
            $table->unsignedSmallInteger('weekday');
            $table->time('start_time');
            $table->unsignedInteger('duration_minutes');
            $table->date('first_date');
            $table->date('last_date');
            $table->enum('status', ['active', 'cancelled', 'ended'])->default('active');
            $table->foreignId('created_by_staff_id')->nullable()->constrained('staff');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('cancelled_at')->nullable();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('guest_name', 150)->nullable();
            $table->string('guest_phone', 20)->nullable();
            $table->foreignId('unit_id')->constrained('bookable_units');
            $table->foreignId('sport_id')->nullable()->constrained('sports');
            $table->foreignId('series_id')->nullable()->constrained('booking_series');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('status', ['pending_deposit', 'confirmed', 'completed', 'cancelled', 'expired', 'no_show']);
            $table->enum('source', ['app', 'front_desk']);
            $table->enum('payment_mode', ['online_full', 'deposit_then_cash', 'cash_full']);

            $table->integer('base_price');
            $table->foreignId('discount_id')->nullable()->constrained('discounts');
            $table->integer('discount_amount')->default(0);
            $table->integer('final_price');
            $table->integer('deposit_amount')->default(0);
            $table->dateTime('deposit_due_at')->nullable();
            $table->integer('paid_amount')->default(0);

            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users');
            $table->foreignId('cancelled_by_staff_id')->nullable()->constrained('staff');
            $table->string('cancel_reason', 255)->nullable();
            $table->integer('refund_amount')->default(0);

            $table->dateTime('reminder_sent_at')->nullable();
            $table->foreignId('created_by_staff_id')->nullable()->constrained('staff');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['unit_id', 'starts_at']);
            $table->index(['user_id', 'starts_at']);
            $table->index(['status', 'deposit_due_at']);
        });

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_customer_check CHECK (user_id IS NOT NULL OR (guest_name IS NOT NULL AND guest_phone IS NOT NULL))');

        Schema::create('space_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('spaces');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->foreignId('booking_id')->nullable()->constrained('bookings');
            $table->foreignId('session_id')->nullable()->constrained('training_sessions');
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();

            $table->index(['space_id', 'is_active', 'starts_at', 'ends_at']);
        });

        DB::statement('ALTER TABLE space_reservations ADD CONSTRAINT space_reservations_owner_check CHECK ((booking_id IS NULL) <> (session_id IS NULL))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('space_reservations');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_series');
    }
};
