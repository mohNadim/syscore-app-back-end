<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Child;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShamCashPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/payments/sham-cash/initiate';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
        $this->user = Sanctum::actingAs(User::factory()->create());
    }

    private function booking(string $state = 'pendingDeposit', array $overrides = []): Booking
    {
        return Booking::factory()->{$state}()->create(array_merge([
            'user_id' => $this->user->id,
            'final_price' => 100000,
            'deposit_amount' => 30000,
        ], $overrides));
    }

    private function subscription(): Subscription
    {
        $enrollment = Enrollment::factory()->create(['child_id' => Child::factory()->for($this->user)->create()->id]);

        return Subscription::factory()->create(['enrollment_id' => $enrollment->id, 'final_price' => 80000]);
    }

    public function test_initiates_deposit_payment(): void
    {
        $booking = $this->booking();

        $this->postJson(self::URL, ['booking_id' => $booking->id])
            ->assertCreated()
            ->assertJsonPath('data.purpose', 'booking_deposit')
            ->assertJsonPath('data.amount', 30000)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.method', 'sham_cash')
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.refunded_amount', 0)
            ->assertJsonMissingPath('data.provider_payload')
            ->assertJsonStructure(['data' => ['id', 'receipt_number', 'provider_reference', 'checkout_url', 'created_at']]);

        $payment = Payment::query()->firstOrFail();
        $this->assertSame($this->user->id, $payment->user_id);
        $this->assertMatchesRegularExpression('/^RC\d{6}[A-Z0-9]{6}$/', $payment->receipt_number);
        $this->assertStringStartsWith('SC-', $payment->provider_reference);
        $this->assertStringContainsString('?ref='.$payment->provider_reference, $payment->provider_payload['checkout_url']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.initiated', 'entity_id' => $payment->id]);
    }

    public function test_initiates_full_payment(): void
    {
        $booking = $this->booking(overrides: ['payment_mode' => 'online_full', 'deposit_amount' => 100000]);

        $this->postJson(self::URL, ['booking_id' => $booking->id])
            ->assertCreated()
            ->assertJsonPath('data.purpose', 'booking_full')
            ->assertJsonPath('data.amount', 100000);
    }

    public function test_initiates_balance_payment(): void
    {
        $booking = $this->booking('pendingDeposit', ['status' => 'confirmed', 'paid_amount' => 30000]);

        $this->postJson(self::URL, ['booking_id' => $booking->id])
            ->assertCreated()
            ->assertJsonPath('data.purpose', 'booking_balance')
            ->assertJsonPath('data.amount', 70000);
    }

    public function test_initiates_subscription_payment(): void
    {
        $subscription = $this->subscription();

        $this->postJson(self::URL, ['subscription_id' => $subscription->id])
            ->assertCreated()
            ->assertJsonPath('data.purpose', 'subscription')
            ->assertJsonPath('data.amount', 80000)
            ->assertJsonPath('data.subscription_id', $subscription->id);
    }

    public function test_reinitiating_returns_the_same_pending_payment(): void
    {
        $booking = $this->booking();

        $first = $this->postJson(self::URL, ['booking_id' => $booking->id])->assertCreated()->json('data');
        $second = $this->postJson(self::URL, ['booking_id' => $booking->id])->assertCreated()->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame($first['checkout_url'], $second['checkout_url']);
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'payment.initiated')->count());
    }

    public function test_missing_fields_use_the_request_locale(): void
    {
        $this->postJson(self::URL, [])->assertUnprocessable()
            ->assertJsonPath('errors.booking_id.0', __('payments.validation.target_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')->postJson(self::URL, [])->assertUnprocessable()
            ->assertJsonPath('errors.booking_id.0', 'Send either a booking_id or a subscription_id.');
    }

    public function test_wrong_types_and_both_ids_are_rejected(): void
    {
        $this->postJson(self::URL, ['booking_id' => 'abc'])->assertUnprocessable()
            ->assertJsonPath('errors.booking_id.0', __('payments.validation.booking_id_invalid', [], 'ar'));
        $this->postJson(self::URL, ['subscription_id' => [1]])->assertUnprocessable()
            ->assertJsonPath('errors.subscription_id.0', __('payments.validation.subscription_id_invalid', [], 'ar'));
        $this->postJson(self::URL, ['booking_id' => 1, 'subscription_id' => 1])->assertUnprocessable()
            ->assertJsonPath('errors.booking_id.0', __('payments.validation.target_exclusive', [], 'ar'));
    }

    public function test_requires_a_user_token(): void
    {
        $booking = $this->booking();

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->postJson(self::URL, ['booking_id' => $booking->id])->assertUnauthorized();

        Sanctum::actingAs(Staff::factory()->create());
        $this->postJson(self::URL, ['booking_id' => $booking->id])->assertForbidden();
    }

    public function test_missing_and_foreign_targets_are_not_found(): void
    {
        $foreign = Booking::factory()->pendingDeposit()->create(['deposit_amount' => 1000]);
        $foreignSubscription = Subscription::factory()->create();

        $this->postJson(self::URL, ['booking_id' => 999999])->assertNotFound();
        $this->postJson(self::URL, ['booking_id' => $foreign->id])->assertNotFound();
        $this->postJson(self::URL, ['subscription_id' => $foreignSubscription->id])->assertNotFound();
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_unpayable_targets_return_422(): void
    {
        $cancelled = $this->booking('cancelled');
        $settled = $this->booking('pendingDeposit', ['status' => 'confirmed', 'paid_amount' => 100000]);

        $this->postJson(self::URL, ['booking_id' => $cancelled->id])->assertUnprocessable()
            ->assertJsonPath('message', __('payments.not_payable', [], 'ar'));
        $this->postJson(self::URL, ['booking_id' => $settled->id])->assertUnprocessable();
    }

    public function test_payments_are_listed_and_shown_for_the_owner_only(): void
    {
        $old = Payment::factory()->create(['user_id' => $this->user->id, 'created_at' => now()->subDay()]);
        $new = Payment::factory()->create(['user_id' => $this->user->id]);
        $foreign = Payment::factory()->create();

        $this->getJson('/api/v1/payments')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.1.id', $old->id);
        $this->getJson('/api/v1/payments?per_page=0')->assertUnprocessable()
            ->assertJsonPath('errors.per_page.0', __('payments.validation.per_page_invalid', [], 'ar'));
        $this->getJson("/api/v1/payments/{$new->id}")->assertOk()->assertJsonPath('data.id', $new->id);
        $this->getJson("/api/v1/payments/{$foreign->id}")->assertNotFound();
        $this->getJson('/api/v1/payments/999999')->assertNotFound();
    }
}
