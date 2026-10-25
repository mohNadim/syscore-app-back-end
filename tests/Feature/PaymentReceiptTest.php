<?php

namespace Tests\Feature;

use App\Models\Academy;
use App\Models\AcademyGroup;
use App\Models\BookableUnit;
use App\Models\Booking;
use App\Models\Child;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
        $this->user = User::factory()->create(['full_name' => 'Layla Haddad', 'phone' => '0911111111']);
        Sanctum::actingAs($this->user);
    }

    private function userReceiptUrl(Payment $payment): string
    {
        return "/api/v1/payments/{$payment->id}/receipt";
    }

    private function adminReceiptUrl(Payment $payment): string
    {
        return "/api/v1/admin/payments/{$payment->id}/receipt";
    }

    private function bookingPayment(array $overrides = []): Payment
    {
        $unit = BookableUnit::factory()->create(['name_ar' => 'ملعب الأول', 'name_en' => 'Court One']);
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'unit_id' => $unit->id,
            'booking_number' => 'BK-20261025',
            'starts_at' => '2026-10-26 10:00:00',
            'ends_at' => '2026-10-26 11:00:00',
        ]);

        return Payment::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'booking_id' => $booking->id,
            'receipt_number' => 'RCPT-00000001',
            'amount' => 100000,
            'paid_at' => '2026-10-25 09:00:00',
        ], $overrides));
    }

    private function subscriptionPayment(): Payment
    {
        $academy = Academy::factory()->create(['name_ar' => 'أكاديمية النجوم', 'name_en' => 'Stars Academy']);
        $group = AcademyGroup::factory()->create(['academy_id' => $academy->id, 'name_ar' => 'فئة الناشئين', 'name_en' => 'Juniors']);
        $child = Child::factory()->create(['user_id' => $this->user->id, 'full_name' => 'Omar Haddad']);
        $enrollment = Enrollment::factory()->create(['child_id' => $child->id, 'group_id' => $group->id]);
        $subscription = Subscription::factory()->create([
            'enrollment_id' => $enrollment->id,
            'status' => 'active',
            'period_start' => '2026-10-01 00:00:00',
            'period_end' => '2026-10-31 00:00:00',
        ]);

        return Payment::factory()->forSubscription()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'amount' => 80000,
        ]);
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

    public function test_user_receipt_for_a_booking_payment(): void
    {
        $payment = $this->bookingPayment();

        $this->getJson($this->userReceiptUrl($payment))
            ->assertOk()
            ->assertJsonPath('data.receipt_number', 'RCPT-00000001')
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.amount', 100000)
            ->assertJsonPath('data.refunded_amount', 0)
            ->assertJsonPath('data.net_amount', 100000)
            ->assertJsonPath('data.payer.name', 'Layla Haddad')
            ->assertJsonPath('data.payer.phone', '0911111111')
            ->assertJsonPath('data.recorded_by', null)
            ->assertJsonPath('data.item.type', 'booking')
            ->assertJsonPath('data.item.booking_number', 'BK-20261025')
            ->assertJsonPath('data.item.unit_name_ar', 'ملعب الأول')
            ->assertJsonPath('data.item.unit_name_en', 'Court One')
            ->assertJsonPath('data.item.starts_at', '2026-10-26T13:00:00+03:00')
            ->assertJsonPath('data.item.ends_at', '2026-10-26T14:00:00+03:00')
            ->assertJsonPath('data.issued_at', '2026-10-25T12:00:00+03:00')
            ->assertJsonStructure(['data' => ['facility_contact' => ['phone', 'whatsapp', 'address_ar', 'address_en']]]);
    }

    public function test_user_receipt_for_a_subscription_payment(): void
    {
        $payment = $this->subscriptionPayment();

        $this->getJson($this->userReceiptUrl($payment))
            ->assertOk()
            ->assertJsonPath('data.purpose', 'subscription')
            ->assertJsonPath('data.net_amount', 80000)
            ->assertJsonPath('data.item.type', 'subscription')
            ->assertJsonPath('data.item.academy_name_ar', 'أكاديمية النجوم')
            ->assertJsonPath('data.item.academy_name_en', 'Stars Academy')
            ->assertJsonPath('data.item.group_name_ar', 'فئة الناشئين')
            ->assertJsonPath('data.item.group_name_en', 'Juniors')
            ->assertJsonPath('data.item.child_name', 'Omar Haddad')
            ->assertJsonPath('data.item.period_start', '2026-10-01T03:00:00+03:00')
            ->assertJsonPath('data.item.period_end', '2026-10-31T03:00:00+03:00');
    }

    public function test_partially_refunded_payment_shows_net_amount(): void
    {
        $payment = $this->bookingPayment(['status' => 'partially_refunded']);
        Refund::factory()->create(['payment_id' => $payment->id, 'amount' => 30000]);

        $this->getJson($this->userReceiptUrl($payment))
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_refunded')
            ->assertJsonPath('data.amount', 100000)
            ->assertJsonPath('data.refunded_amount', 30000)
            ->assertJsonPath('data.net_amount', 70000);
    }

    public function test_pending_payment_has_no_receipt(): void
    {
        $payment = $this->bookingPayment(['status' => 'pending', 'paid_at' => null]);

        $this->getJson($this->userReceiptUrl($payment))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'لا يتوفر إيصال لهذه الدفعة.');

        $this->withHeader('Accept-Language', 'en')->getJson($this->userReceiptUrl($payment))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No receipt is available for this payment.');
    }

    public function test_requires_a_user_token(): void
    {
        $payment = $this->bookingPayment();

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson($this->userReceiptUrl($payment))->assertUnauthorized();

        Sanctum::actingAs(Staff::factory()->create());
        $this->getJson($this->userReceiptUrl($payment))->assertForbidden();
    }

    public function test_missing_and_foreign_payments_are_not_found(): void
    {
        $foreign = Payment::factory()->create();

        $this->getJson('/api/v1/payments/999999/receipt')->assertNotFound();
        $this->getJson($this->userReceiptUrl($foreign))->assertNotFound();
    }

    public function test_admin_receipt_requires_payments_view(): void
    {
        $payment = $this->bookingPayment();

        Sanctum::actingAs($this->staffWithPermissions(['payments.view']));
        $this->getJson($this->adminReceiptUrl($payment))
            ->assertOk()
            ->assertJsonPath('data.receipt_number', 'RCPT-00000001');

        Sanctum::actingAs($this->staffWithPermissions(['payments.manage']));
        $this->getJson($this->adminReceiptUrl($payment))->assertForbidden();
    }

    public function test_guest_payer_fields_are_shown(): void
    {
        $unit = BookableUnit::factory()->create();
        $booking = Booking::factory()->guest()->create(['unit_id' => $unit->id]);
        $staff = $this->staffWithPermissions(['payments.view']);
        $payment = Payment::factory()->create([
            'user_id' => null,
            'booking_id' => $booking->id,
            'payer_name' => 'Guest Payer',
            'payer_phone' => '0999999999',
            'recorded_by_staff_id' => $staff->id,
        ]);

        Sanctum::actingAs($staff);
        $this->getJson($this->adminReceiptUrl($payment))
            ->assertOk()
            ->assertJsonPath('data.payer.name', 'Guest Payer')
            ->assertJsonPath('data.payer.phone', '0999999999')
            ->assertJsonPath('data.recorded_by', $staff->full_name)
            ->assertJsonPath('data.item.type', 'booking');
    }
}
