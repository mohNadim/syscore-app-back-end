<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Child;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ShamCashWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/webhooks/sham-cash';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function webhook(array $body, ?string $signature = null): TestResponse
    {
        $json = json_encode($body);
        $signature ??= hash_hmac('sha256', $json, config('services.sham_cash.webhook_secret'));

        return $this->call('POST', self::URL, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'ar',
            'HTTP_X-ShamCash-Signature' => $signature,
        ], $json);
    }

    private function pendingBookingPayment(array $bookingState = []): Payment
    {
        $booking = Booking::factory()->pendingDeposit()->create(array_merge(['final_price' => 100000, 'deposit_amount' => 30000], $bookingState));

        return Payment::factory()->pending()->create([
            'user_id' => $booking->user_id,
            'booking_id' => $booking->id,
            'method' => 'sham_cash',
            'purpose' => 'booking_deposit',
            'amount' => 30000,
            'provider_reference' => 'SC-TEST',
            'provider_payload' => ['checkout_url' => 'https://x.test'],
        ]);
    }

    private function pendingSubscriptionPayment(?Subscription $renewedFrom = null): Payment
    {
        $enrollment = $renewedFrom?->enrollment ?? Enrollment::factory()->create(['child_id' => Child::factory()->create()->id]);
        $subscription = Subscription::factory()->create(['enrollment_id' => $enrollment->id, 'final_price' => 80000, 'renewed_from_id' => $renewedFrom?->id]);

        return Payment::factory()->forSubscription()->pending()->create([
            'user_id' => User::factory(),
            'subscription_id' => $subscription->id,
            'method' => 'sham_cash',
            'amount' => 80000,
            'provider_reference' => 'SC-SUB',
        ]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payment = $this->pendingBookingPayment();
        $body = ['reference' => 'SC-TEST', 'status' => 'succeeded', 'amount' => 30000];

        $this->webhook($body, 'bad')->assertUnauthorized()->assertJsonPath('message', __('payments.invalid_signature', [], 'ar'));
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_bad_signature_wins_over_a_malformed_body(): void
    {
        $this->webhook(['status' => 'weird', 'amount' => -1], 'bad')->assertUnauthorized();
        $this->webhook(['status' => 'weird'], '')->assertUnauthorized();
    }

    public function test_success_confirms_a_pending_deposit_booking(): void
    {
        $payment = $this->pendingBookingPayment();

        $this->webhook(['reference' => 'SC-TEST', 'status' => 'succeeded', 'amount' => 30000, 'transaction_id' => 'T1'])
            ->assertOk()
            ->assertJsonPath('data.id', $payment->id)
            ->assertJsonPath('data.status', 'succeeded');

        $payment->refresh();
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('https://x.test', $payment->provider_payload['checkout_url']);
        $this->assertSame('T1', $payment->provider_payload['webhook']['transaction_id']);
        $booking = $payment->booking;
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame(30000, $booking->paid_amount);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.paid', 'entity_id' => $booking->id]);
    }

    public function test_success_activates_a_subscription(): void
    {
        $payment = $this->pendingSubscriptionPayment();

        $this->webhook(['reference' => 'SC-SUB', 'status' => 'succeeded', 'amount' => 80000])->assertOk();

        $subscription = $payment->subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->period_start->eq(now()));
        $this->assertTrue($subscription->period_end->eq(now()->addMonth()));
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.activated', 'entity_id' => $subscription->id]);
    }

    public function test_renewal_starts_at_the_previous_period_end(): void
    {
        $previous = Subscription::factory()->active()->create(['period_start' => now()->subDays(20), 'period_end' => now()->addDays(10)]);
        $payment = $this->pendingSubscriptionPayment($previous);

        $this->webhook(['reference' => 'SC-SUB', 'status' => 'succeeded', 'amount' => 80000])->assertOk();

        $subscription = $payment->subscription->refresh();
        $this->assertTrue($subscription->period_start->eq($previous->period_end));
        $this->assertTrue($subscription->period_end->eq($previous->period_end->copy()->addMonth()));
    }

    public function test_duplicate_webhook_is_not_applied_twice(): void
    {
        $payment = $this->pendingBookingPayment();
        $body = ['reference' => 'SC-TEST', 'status' => 'succeeded', 'amount' => 30000];

        $this->webhook($body)->assertOk();
        $this->webhook($body)->assertOk()->assertJsonPath('data.status', 'succeeded');

        $this->assertSame(30000, $payment->booking->refresh()->paid_amount);
        $this->assertSame(0, $payment->refunds()->count());
    }

    public function test_amount_mismatch_fails_the_payment_and_leaves_the_booking(): void
    {
        $payment = $this->pendingBookingPayment();

        $this->webhook(['reference' => 'SC-TEST', 'status' => 'succeeded', 'amount' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'failed');

        $this->assertSame('pending_deposit', $payment->booking->refresh()->status);
        $this->assertSame(0, $payment->booking->paid_amount);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.amount_mismatch', 'entity_id' => $payment->id]);
    }

    public function test_failed_status_marks_the_payment_failed(): void
    {
        $payment = $this->pendingBookingPayment();

        $this->webhook(['reference' => 'SC-TEST', 'status' => 'failed', 'amount' => 30000])->assertOk()->assertJsonPath('data.status', 'failed');

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('pending_deposit', $payment->booking->refresh()->status);
    }

    public function test_unknown_reference_is_not_found(): void
    {
        $this->webhook(['reference' => 'NOPE', 'status' => 'succeeded', 'amount' => 1])->assertNotFound();
    }

    public function test_invalid_body_is_rejected(): void
    {
        $this->webhook(['status' => 'weird', 'amount' => -1])->assertUnprocessable()
            ->assertJsonValidationErrors(['reference', 'status', 'amount']);
    }

    public function test_success_for_a_cancelled_booking_is_refunded(): void
    {
        $payment = $this->pendingBookingPayment();
        $payment->booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $this->webhook(['reference' => 'SC-TEST', 'status' => 'succeeded', 'amount' => 30000])->assertOk()->assertJsonPath('data.status', 'refunded');

        $payment->refresh();
        $this->assertSame('refunded', $payment->status);
        $refund = $payment->refunds()->firstOrFail();
        $this->assertSame(30000, $refund->amount);
        $this->assertSame('target_not_payable', $refund->reason);
        $this->assertStringStartsWith('SCR-', $refund->provider_reference);
        $booking = $payment->booking->refresh();
        $this->assertSame(30000, $booking->refund_amount);
        $this->assertSame(0, $booking->paid_amount);
        $this->assertSame('cancelled', $booking->status);
    }

    public function test_overpayment_is_refunded(): void
    {
        $payment = $this->pendingBookingPayment(['paid_amount' => 90000]);

        $this->webhook(['reference' => 'SC-TEST', 'status' => 'succeeded', 'amount' => 30000])->assertOk();

        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame(90000, $payment->booking->refresh()->paid_amount);
    }

    public function test_payment_for_a_non_pending_subscription_is_refunded(): void
    {
        $payment = $this->pendingSubscriptionPayment();
        $payment->subscription->update(['status' => 'cancelled']);

        $this->webhook(['reference' => 'SC-SUB', 'status' => 'succeeded', 'amount' => 80000])->assertOk();

        $this->assertSame('refunded', $payment->fresh()->status);
    }
}
