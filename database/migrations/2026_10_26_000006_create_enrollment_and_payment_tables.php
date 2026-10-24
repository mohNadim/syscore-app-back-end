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
     * `active_key` is a generated column that is NULL for non-active enrollments.
     * Its unique index enforces one active enrollment per child and group, since
     * NULL values never collide in a unique index.
     */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children');
            $table->foreignId('group_id')->constrained('academy_groups');
            $table->enum('status', ['active', 'ended', 'cancelled'])->default('active');
            $table->dateTime('enrolled_at')->useCurrent();
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('created_by_staff_id')->nullable()->constrained('staff');
            $table->string('active_key', 100)->nullable()->storedAs("if(status = 'active', concat(child_id, ':', group_id), null)");

            $table->index(['child_id', 'group_id']);
            $table->unique('active_key');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments');
            $table->enum('status', ['pending_payment', 'active', 'expired', 'cancelled']);
            $table->dateTime('period_start')->nullable();
            $table->dateTime('period_end')->nullable();
            $table->integer('base_price');
            $table->foreignId('discount_id')->nullable()->constrained('discounts');
            $table->integer('discount_amount')->default(0);
            $table->integer('final_price');
            $table->foreignId('renewed_from_id')->nullable()->constrained('subscriptions');
            $table->dateTime('reminder_7d_sent_at')->nullable();
            $table->dateTime('expiry_notice_sent_at')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['status', 'period_end']);
        });

        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('academy_groups');
            $table->foreignId('child_id')->constrained('children');
            $table->enum('status', ['waiting', 'offered', 'enrolled', 'removed'])->default('waiting');
            $table->dateTime('offered_at')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['group_id', 'child_id']);
            $table->index(['group_id', 'created_at']);
        });

        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('training_sessions');
            $table->foreignId('child_id')->constrained('children');
            $table->enum('status', ['present', 'absent']);
            $table->foreignId('marked_by_staff_id')->constrained('staff');
            $table->dateTime('marked_at')->useCurrent();

            $table->unique(['session_id', 'child_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('payer_name', 150)->nullable();
            $table->string('payer_phone', 20)->nullable();
            $table->integer('amount');
            $table->enum('method', ['sham_cash', 'cash']);
            $table->enum('purpose', ['booking_deposit', 'booking_full', 'booking_balance', 'subscription']);
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded', 'partially_refunded']);
            $table->foreignId('booking_id')->nullable()->constrained('bookings');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions');
            $table->string('provider_reference', 100)->nullable()->unique();
            $table->json('provider_payload')->nullable();
            $table->foreignId('recorded_by_staff_id')->nullable()->constrained('staff');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['status', 'created_at']);
            $table->index(['method', 'paid_at']);
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_owner_check CHECK ((booking_id IS NULL) <> (subscription_id IS NULL))');

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments');
            $table->integer('amount');
            $table->enum('method', ['sham_cash', 'cash']);
            $table->string('reason', 255)->nullable();
            $table->string('provider_reference', 100)->nullable();
            $table->foreignId('processed_by_staff_id')->nullable()->constrained('staff');
            $table->dateTime('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('waitlist_entries');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('enrollments');
    }
};
