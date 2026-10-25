<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Child;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/admin/payments';

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));
        $this->staff = $this->staffWithPermissions(['payments.view', 'payments.manage']);
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function subscription(array $overrides = []): Subscription
    {
        $enrollment = Enrollment::factory()->create(['child_id' => Child::factory()->create()->id]);

        return Subscription::factory()->create(array_merge(['enrollment_id' => $enrollment->id, 'final_price' => 80000], $overrides));
    }

    public function test_cash_pays_a_front_desk_booking_in_full(): void
    {
        $booking = Booking::factory()->guest()->create(['final_price' => 100000]);

        $this->postJson(self::URL, ['booking_id' => $booking->id])
            ->assertCreated()
            ->assertJsonPath('message', __('admin_payments.cash_recorded', [], 'ar'))
            ->assertJsonPath('data.method', 'cash')
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.purpose', 'booking_full')
            ->assertJsonPath('data.amount', 100000)
            ->assertJsonPath('data.payer_name', $booking->guest_name)
            ->assertJsonPath('data.payer_phone', $booking->guest_phone)
            ->assertJsonPath('data.recorded_by.id', $this->staff->id);

        $payment = Payment::query()->firstOrFail();
        $this->assertNull($payment->user_id);
        $this->assertSame($this->staff->id, $payment->recorded_by_staff_id);
        $this->assertNotNull($payment->paid_at);
        $this->assertMatchesRegularExpression('/^RC\d{6}[A-Z0-9]{6}$/', $payment->receipt_number);
        $this->assertSame(100000, $booking->refresh()->paid_amount);
        $this->assertSame('confirmed', $booking->status);
        $this->assertTrue(AuditLog::query()->where('action', 'payment.cash_recorded')->where('entity_id', $payment->id)->exists());
    }

    public function test_cash_deposit_confirms_a_pending_booking_then_the_balance_is_paid(): void
    {
        $booking = Booking::factory()->pendingDeposit()->create(['final_price' => 100000, 'deposit_amount' => 30000]);

        $this->postJson(self::URL, ['booking_id' => $booking->id, 'amount' => 30000, 'payer_name' => 'Ali', 'payer_phone' => '0911'])
            ->assertCreated()
            ->assertJsonPath('data.purpose', 'booking_deposit')
            ->assertJsonPath('data.payer_name', 'Ali')
            ->assertJsonPath('data.user.id', $booking->user_id);

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame(30000, $booking->paid_amount);

        $this->postJson(self::URL, ['booking_id' => $booking->id])
            ->assertCreated()
            ->assertJsonPath('data.purpose', 'booking_balance')
            ->assertJsonPath('data.amount', 70000);

        $this->assertSame(100000, $booking->refresh()->paid_amount);

        $this->postJson(self::URL, ['booking_id' => $booking->id])->assertUnprocessable()
            ->assertJsonPath('message', __('payments.not_payable', [], 'ar'));
    }

    public function test_cash_balance_can_be_taken_on_a_completed_booking(): void
    {
        $booking = Booking::factory()->create(['status' => 'completed', 'final_price' => 100000, 'paid_amount' => 30000]);

        $this->postJson(self::URL, ['booking_id' => $booking->id])->assertCreated()->assertJsonPath('data.amount', 70000);

        $booking->refresh();
        $this->assertSame('completed', $booking->status);
        $this->assertSame(100000, $booking->paid_amount);
        $this->assertSame(0, Payment::query()->firstOrFail()->refunds()->count());
    }

    public function test_cash_activates_a_subscription(): void
    {
        $subscription = $this->subscription();

        $this->postJson(self::URL, ['subscription_id' => $subscription->id, 'amount' => 80000])
            ->assertCreated()
            ->assertJsonPath('data.purpose', 'subscription')
            ->assertJsonPath('data.subscription_id', $subscription->id)
            ->assertJsonPath('data.user.id', $subscription->enrollment->child->user_id);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->period_end->eq(now()->addMonth()));
    }

    public function test_amount_rules_are_enforced(): void
    {
        $booking = Booking::factory()->pendingDeposit()->create(['final_price' => 100000, 'deposit_amount' => 30000]);

        $this->postJson(self::URL, ['booking_id' => $booking->id, 'amount' => 100001])->assertUnprocessable()
            ->assertJsonPath('message', __('payments.amount_exceeds_remaining', [], 'ar'));
        $this->postJson(self::URL, ['booking_id' => $booking->id, 'amount' => 10000])->assertUnprocessable()
            ->assertJsonPath('message', __('payments.amount_below_deposit', [], 'ar'));

        $subscription = $this->subscription();
        $this->postJson(self::URL, ['subscription_id' => $subscription->id, 'amount' => 1000])->assertUnprocessable()
            ->assertJsonPath('message', __('payments.amount_must_match_price', [], 'ar'));

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_unpayable_targets_are_rejected(): void
    {
        $cancelled = Booking::factory()->cancelled()->create();
        $active = $this->subscription(['status' => 'active']);

        $this->postJson(self::URL, ['booking_id' => $cancelled->id])->assertUnprocessable()
            ->assertJsonPath('message', __('payments.not_payable', [], 'ar'));
        $this->postJson(self::URL, ['subscription_id' => $active->id])->assertUnprocessable();
    }

    public function test_validation_messages_follow_the_locale(): void
    {
        $this->postJson(self::URL, [])->assertUnprocessable()
            ->assertJsonPath('errors.booking_id.0', __('payments.validation.target_required', [], 'ar'));
        $this->postJson(self::URL, ['booking_id' => 1, 'subscription_id' => 1])->assertUnprocessable()->assertJsonValidationErrors(['booking_id', 'subscription_id']);
        $this->postJson(self::URL, ['booking_id' => 1, 'amount' => 0, 'payer_name' => str_repeat('a', 151), 'payer_phone' => str_repeat('1', 21)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', __('admin_payments.validation.amount_invalid', [], 'ar'))
            ->assertJsonValidationErrors(['payer_name', 'payer_phone']);
        $this->withHeader('Accept-Language', 'en')->postJson(self::URL, [])->assertUnprocessable()
            ->assertJsonPath('errors.booking_id.0', __('payments.validation.target_required', [], 'en'));
    }

    public function test_unknown_targets_are_not_found(): void
    {
        $this->postJson(self::URL, ['booking_id' => 999999])->assertNotFound();
        $this->postJson(self::URL, ['subscription_id' => 999999])->assertNotFound();
        $this->getJson(self::URL.'/999999')->assertNotFound();
    }

    public function test_access_control(): void
    {
        $booking = Booking::factory()->create();
        $this->app['auth']->forgetGuards();

        $this->withToken('bad')->getJson(self::URL)->assertUnauthorized();

        Sanctum::actingAs($this->staffWithPermissions(['payments.view']));
        $this->postJson(self::URL, ['booking_id' => $booking->id])->assertForbidden();

        Sanctum::actingAs($this->staffWithPermissions(['payments.manage']));
        $this->getJson(self::URL)->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL, ['booking_id' => $booking->id])->assertForbidden();
    }

    public function test_list_filters_and_show(): void
    {
        $booking = Booking::factory()->create();
        $subscription = $this->subscription();
        $cash = Payment::factory()->create(['booking_id' => $booking->id, 'user_id' => $booking->user_id, 'receipt_number' => 'RC261026AAAAAA', 'payer_phone' => '+963911111111', 'recorded_by_staff_id' => $this->staff->id, 'created_at' => '2026-10-20 12:00:00']);
        $online = Payment::factory()->forSubscription()->create(['subscription_id' => $subscription->id, 'method' => 'sham_cash', 'status' => 'refunded', 'created_at' => '2026-10-25 12:00:00']);
        $this->postJson(self::URL.'/'.$cash->id.'/refunds')->assertForbidden();

        $this->getJson(self::URL)->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $online->id)
            ->assertJsonPath('data.1.id', $cash->id);
        $this->getJson(self::URL.'?status=refunded')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $online->id);
        $this->getJson(self::URL.'?method=cash')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $cash->id);
        $this->getJson(self::URL.'?purpose=subscription')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'?booking_id='.$booking->id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'?subscription_id='.$subscription->id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'?user_id='.$booking->user_id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'?from=2026-10-24&to=2026-10-26')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $online->id);
        $this->getJson(self::URL.'?q=AAAAAA')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $cash->id);
        $this->getJson(self::URL.'?q=1111')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'?status=weird&to=2026-01-01&from=2026-02-01')->assertUnprocessable()->assertJsonValidationErrors(['status', 'to']);

        $this->getJson(self::URL.'/'.$cash->id)->assertOk()
            ->assertJsonPath('data.id', $cash->id)
            ->assertJsonPath('data.user.id', $booking->user_id)
            ->assertJsonPath('data.recorded_by.id', $this->staff->id)
            ->assertJsonPath('data.refunds', []);
    }
}
