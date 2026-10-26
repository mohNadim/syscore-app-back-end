<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/admin/audit-logs';

    private Staff $staff;

    private User $user;

    private AuditLog $staffPaymentLog;

    private AuditLog $userBookingLog;

    private AuditLog $systemPaymentLog;

    private AuditLog $oldNewsLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');

        // Sunday 2026-10-26 11:00 in Damascus (UTC+3).
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));

        $this->staff = $this->staffWithPermissions(['audit_logs.view']);
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->staff);

        // Local 2026-10-25 23:00 (Damascus), the day before the local range below.
        $this->staffPaymentLog = $this->entry([
            'actor_type' => 'staff',
            'actor_staff_id' => $this->staff->id,
            'action' => 'payment.cash_recorded',
            'entity_type' => 'payment',
            'entity_id' => 5,
            'after' => ['amount' => 100000],
        ], '2026-10-25 20:00:00');

        // Local 2026-10-26 01:00 (Damascus).
        $this->userBookingLog = $this->entry([
            'actor_type' => 'user',
            'actor_user_id' => $this->user->id,
            'action' => 'booking.cancelled',
            'entity_type' => 'booking',
            'entity_id' => 7,
            'before' => ['status' => 'confirmed'],
            'after' => ['status' => 'cancelled'],
        ], '2026-10-25 22:00:00');

        // Local 2026-10-26 13:00 (Damascus).
        $this->systemPaymentLog = $this->entry([
            'actor_type' => 'system',
            'action' => 'payment.cash_recorded',
            'entity_type' => 'payment',
            'entity_id' => 6,
        ], '2026-10-26 10:00:00');

        $this->oldNewsLog = $this->entry([
            'actor_type' => 'staff',
            'actor_staff_id' => $this->staff->id,
            'action' => 'news.updated',
            'entity_type' => 'news',
            'entity_id' => 2,
        ], '2026-10-20 10:00:00');
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
     * @param  array<string, mixed>  $attributes
     */
    private function entry(array $attributes, string $createdAtUtc): AuditLog
    {
        $log = AuditLog::factory()->create($attributes);
        $log->forceFill(['created_at' => CarbonImmutable::parse($createdAtUtc, 'UTC')])->save();

        return $log;
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withHeader('Accept-Language', 'ar')->getJson(self::URL)->assertUnauthorized();
        $this->withHeader('Accept-Language', 'ar')->getJson(self::URL.'/'.$this->userBookingLog->id)->assertUnauthorized();

        Sanctum::actingAs($this->staffWithPermissions(['payments.view']));

        $this->getJson(self::URL)->assertForbidden();
        $this->getJson(self::URL.'/'.$this->userBookingLog->id)->assertForbidden();

        Sanctum::actingAs($this->user);

        $this->getJson(self::URL)->assertForbidden();
        $this->getJson(self::URL.'/'.$this->userBookingLog->id)->assertForbidden();
    }

    public function test_entries_are_listed_newest_first_with_actors_and_local_times(): void
    {
        $response = $this->getJson(self::URL)->assertOk();

        $response->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.0.id', $this->systemPaymentLog->id)
            ->assertJsonPath('data.0.actor_type', 'system')
            ->assertJsonPath('data.0.actor', null)
            ->assertJsonPath('data.0.created_at', '2026-10-26T13:00:00+03:00')
            ->assertJsonPath('data.1.id', $this->userBookingLog->id)
            ->assertJsonPath('data.1.actor.type', 'user')
            ->assertJsonPath('data.1.actor.id', $this->user->id)
            ->assertJsonPath('data.1.before.status', 'confirmed')
            ->assertJsonPath('data.1.after.status', 'cancelled')
            ->assertJsonPath('data.2.id', $this->staffPaymentLog->id)
            ->assertJsonPath('data.2.actor.type', 'staff')
            ->assertJsonPath('data.2.actor.id', $this->staff->id)
            ->assertJsonPath('data.3.id', $this->oldNewsLog->id);

        $this->assertArrayHasKey('ip_address', $response->json('data.0'));
    }

    public function test_entries_are_paginated(): void
    {
        $this->getJson(self::URL.'?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.id', $this->systemPaymentLog->id);
    }

    public function test_entries_can_be_filtered_by_actor_type_and_ids(): void
    {
        $this->getJson(self::URL.'?actor_type=staff')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $this->staffPaymentLog->id)
            ->assertJsonPath('data.1.id', $this->oldNewsLog->id);

        $this->getJson(self::URL.'?actor_type=system')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->systemPaymentLog->id);

        $this->getJson(self::URL.'?actor_user_id='.$this->user->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->userBookingLog->id);

        $this->getJson(self::URL.'?actor_staff_id='.$this->staff->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson(self::URL.'?actor_user_id=999999')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_entries_can_be_filtered_by_action_and_audited_entity(): void
    {
        $this->getJson(self::URL.'?action=payment.cash_recorded')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson(self::URL.'?entity_type=payment&entity_id=5')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->staffPaymentLog->id);

        $this->getJson(self::URL.'?entity_type=payment')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_entries_can_be_filtered_by_local_date_range(): void
    {
        // Local day 2026-10-26 spans UTC 2026-10-25 21:00 to 2026-10-26 20:59:59.
        $this->getJson(self::URL.'?date_from=2026-10-26&date_to=2026-10-26')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $this->systemPaymentLog->id)
            ->assertJsonPath('data.1.id', $this->userBookingLog->id);

        $this->getJson(self::URL.'?date_from=2026-10-20&date_to=2026-10-25')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $this->staffPaymentLog->id)
            ->assertJsonPath('data.1.id', $this->oldNewsLog->id);

        $this->getJson(self::URL.'?date_from=2026-10-27')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_filters_are_combined(): void
    {
        $this->getJson(self::URL.'?actor_type=staff&action=payment.cash_recorded&entity_type=payment')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->staffPaymentLog->id);
    }

    public function test_invalid_filters_are_rejected_in_both_languages(): void
    {
        $this->getJson(self::URL.'?actor_type=robot&actor_user_id=0&entity_id=abc&date_from=26-10-2026&per_page=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['actor_type', 'actor_user_id', 'entity_id', 'date_from', 'per_page'])
            ->assertJsonPath('errors.actor_type.0', __('admin_audit_logs.validation.actor_type_invalid', [], 'ar'));

        $response = $this->withHeader('Accept-Language', 'en')
            ->getJson(self::URL.'?actor_type=robot&actor_staff_id=x&action='.str_repeat('a', 101).'&entity_type='.str_repeat('a', 51).'&date_from=2026-10-27&date_to=2026-10-26')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['actor_type', 'actor_staff_id', 'action', 'entity_type', 'date_to']);

        $this->assertSame('The actor type must be one of: user, staff, system.', $response->json('errors')['actor_type'][0]);
        $this->assertSame('The end date cannot be before the start date.', $response->json('errors')['date_to'][0]);
    }

    public function test_entry_is_shown_with_its_actor(): void
    {
        $this->getJson(self::URL.'/'.$this->userBookingLog->id)
            ->assertOk()
            ->assertJsonPath('data.id', $this->userBookingLog->id)
            ->assertJsonPath('data.action', 'booking.cancelled')
            ->assertJsonPath('data.entity_type', 'booking')
            ->assertJsonPath('data.entity_id', 7)
            ->assertJsonPath('data.actor.id', $this->user->id)
            ->assertJsonPath('data.before.status', 'confirmed')
            ->assertJsonPath('data.after.status', 'cancelled');
    }

    public function test_missing_entry_returns_not_found(): void
    {
        $this->getJson(self::URL.'/999999')->assertNotFound();
    }
}
