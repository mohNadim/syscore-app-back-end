<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBookingFixtures;
use Tests\TestCase;

class AdminRefundTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createBookingFixtures();
        $this->staff = $this->staffWithPermissions(['refunds.manage']);
        Sanctum::actingAs($this->staff);
    }

    /**
     * @param  list<string>  $codes
     */
    private function staffWithPermissions(array $codes): Staff
    {
        $role = Role::factory()->create(['name_ar' => 'دور '.fake()->unique()->numerify('####'), 'name_en' => 'Role']);

        foreach ($codes as $code) {
            $role->permissions()->attach(Permission::query()->firstOrCreate(['code' => $code], ['description' => $code]));
        }

        return Staff::factory()->create(['role_id' => $role->id]);
    }

    private function url(Payment $payment): string
    {
        return "/api/v1/admin/payments/{$payment->id}/refunds";
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function paidBooking(string $method = 'sham_cash', array $overrides = []): Payment
    {
        $booking = Booking::factory()->create(['final_price' => 100000, 'paid_amount' => 100000]);

        return Payment::factory()->create(array_merge([
            'user_id' => $booking->user_id,
            'booking_id' => $booking->id,
            'method' => $method,
            'amount' => 100000,
            'provider_reference' => $method === 'sham_cash' ? 'SC-REF-'.fake()->unique()->numerify('######') : null,
        ], $overrides));
    }

    public function test_partial_then_full_refund_updates_status_and_booking(): void
    {
        $payment = $this->paidBooking();

        $this->postJson($this->url($payment), ['amount' => 40000, 'reason' => 'goodwill'])
            ->assertCreated()
            ->assertJsonPath('message', __('admin_payments.refunded', [], 'ar'))
            ->assertJsonPath('data.status', 'partially_refunded')
            ->assertJsonPath('data.refunded_amount', 40000)
            ->assertJsonPath('data.refunds.0.amount', 40000)
            ->assertJsonPath('data.refunds.0.reason', 'goodwill')
            ->assertJsonPath('data.refunds.0.processed_by_staff_id', $this->staff->id);

        $this->assertStringStartsWith('SCR-', $payment->refunds()->firstOrFail()->provider_reference);
        $this->assertSame(40000, $payment->booking->refresh()->refund_amount);

        $this->postJson($this->url($payment), ['amount' => 60001])->assertUnprocessable()
            ->assertJsonPath('message', __('payments.refund_exceeds', [], 'ar'));

        $this->postJson($this->url($payment), ['amount' => 60000])->assertCreated()
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonCount(2, 'data.refunds');

        $this->assertSame(100000, $payment->booking->refresh()->refund_amount);
        $this->postJson($this->url($payment), ['amount' => 1])->assertUnprocessable()
            ->assertJsonPath('message', __('payments.not_refundable', [], 'ar'));
    }

    public function test_cash_refund_has_no_provider_reference(): void
    {
        $payment = $this->paidBooking('cash');

        $this->postJson($this->url($payment), ['amount' => 1000])->assertCreated();

        $this->assertNull($payment->refunds()->firstOrFail()->provider_reference);
    }

    public function test_pending_and_failed_payments_are_not_refundable(): void
    {
        foreach (['pending', 'failed'] as $status) {
            $payment = $this->paidBooking('sham_cash', ['status' => $status]);

            $this->postJson($this->url($payment), ['amount' => 1000])->assertUnprocessable();
        }
    }

    public function test_validation_access_and_not_found(): void
    {
        $payment = $this->paidBooking();

        $this->postJson($this->url($payment), [])->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', __('admin_payments.validation.refund_amount_invalid', [], 'ar'));
        $this->withHeader('Accept-Language', 'en')->postJson($this->url($payment), ['amount' => 0, 'reason' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', __('admin_payments.validation.refund_amount_invalid', [], 'en'))
            ->assertJsonValidationErrors(['reason']);
        $this->postJson('/api/v1/admin/payments/999999/refunds', ['amount' => 1])->assertNotFound();

        Sanctum::actingAs($this->staffWithPermissions(['payments.manage', 'payments.view']));
        $this->postJson($this->url($payment), ['amount' => 1000])->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson($this->url($payment), ['amount' => 1000])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken('bad')->postJson($this->url($payment), ['amount' => 1000])->assertUnauthorized();
    }

    public function test_customer_cancellation_refunds_online_payments_but_staff_cancellation_does_not(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));
        $user = User::factory()->create();
        $make = function () use ($user): Payment {
            $booking = Booking::factory()->create([
                'user_id' => $user->id,
                'unit_id' => $this->fullUnit->id,
                'starts_at' => now()->addDays(5),
                'ends_at' => now()->addDays(5)->addHour(),
                'final_price' => 100000,
                'paid_amount' => 100000,
            ]);

            return Payment::factory()->create(['user_id' => $user->id, 'booking_id' => $booking->id, 'method' => 'sham_cash', 'amount' => 100000, 'provider_reference' => 'SC-X'.$booking->id]);
        };

        $byUser = $make();
        $byStaff = $make();

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/bookings/{$byUser->booking_id}/cancel")->assertOk();

        $byUser->refresh();
        $this->assertSame('refunded', $byUser->status);
        $refund = $byUser->refunds()->firstOrFail();
        $this->assertSame('customer_cancellation', $refund->reason);
        $this->assertNull($refund->processed_by_staff_id);
        $this->assertSame(100000, $byUser->booking->refresh()->refund_amount);

        Sanctum::actingAs(Staff::factory()->create(['role_id' => $this->staffWithPermissions(['bookings.manage'])->role_id]));
        $this->postJson("/api/v1/admin/bookings/{$byStaff->booking_id}/cancel", ['reason' => 'maintenance'])->assertOk();

        $this->assertSame('succeeded', $byStaff->refresh()->status);
        $this->assertSame(0, $byStaff->refunds()->count());
    }
}
