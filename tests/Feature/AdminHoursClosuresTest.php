<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Closure;
use App\Models\Facility;
use App\Models\OperatingHour;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Space;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminHoursClosuresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
    }

    /**
     * Create a staff member whose role holds the given permission codes.
     *
     * @param  list<string>  $codes
     * @param  array<string, mixed>  $attributes
     */
    private function staffWithPermissions(array $codes, array $attributes = []): Staff
    {
        $role = Role::factory()->create(['name_ar' => 'دور '.fake()->unique()->numerify('####'), 'name_en' => 'Role']);

        foreach ($codes as $code) {
            $permission = Permission::query()->firstOrCreate(['code' => $code], ['description' => $code]);
            $role->permissions()->attach($permission);
        }

        return Staff::factory()->create(['role_id' => $role->id, ...$attributes]);
    }

    private function actingAsHoursManager(): Staff
    {
        $staff = $this->staffWithPermissions(['hours.manage']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    private function hoursUrl(Facility $facility): string
    {
        return "/api/v1/admin/facilities/{$facility->id}/operating-hours";
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $facility = Facility::factory()->create();
        $closure = Closure::factory()->create(['facility_id' => $facility->id]);

        $endpoints = [
            ['getJson', $this->hoursUrl($facility)],
            ['putJson', $this->hoursUrl($facility)],
            ['getJson', '/api/v1/admin/closures'],
            ['postJson', '/api/v1/admin/closures'],
            ['getJson', "/api/v1/admin/closures/{$closure->id}"],
            ['putJson', "/api/v1/admin/closures/{$closure->id}"],
            ['deleteJson', "/api/v1/admin/closures/{$closure->id}"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertUnauthorized();
        }

        Sanctum::actingAs(User::factory()->create());
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }

        Sanctum::actingAs($this->staffWithPermissions(['sports.manage']));
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    public function test_hours_index_is_ordered_by_weekday_with_short_times(): void
    {
        $this->actingAsHoursManager();
        $facility = Facility::factory()->create();
        OperatingHour::factory()->create(['facility_id' => $facility->id, 'weekday' => 5, 'open_time' => '09:30:00', 'close_time' => '23:00:00']);
        OperatingHour::factory()->create(['facility_id' => $facility->id, 'weekday' => 1, 'open_time' => '08:00:00', 'close_time' => '22:00:00']);

        $this->getJson($this->hoursUrl($facility))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.weekday', 1)
            ->assertJsonPath('data.0.open_time', '08:00')
            ->assertJsonPath('data.1.weekday', 5)
            ->assertJsonPath('data.1.open_time', '09:30')
            ->assertJsonPath('data.1.close_time', '23:00');
    }

    public function test_hours_update_replaces_the_whole_week_and_audits(): void
    {
        $this->actingAsHoursManager();
        $facility = Facility::factory()->create();
        $other = Facility::factory()->create();
        OperatingHour::factory()->create(['facility_id' => $facility->id, 'weekday' => 0]);
        OperatingHour::factory()->create(['facility_id' => $facility->id, 'weekday' => 2]);
        OperatingHour::factory()->create(['facility_id' => $other->id, 'weekday' => 2]);

        $this->putJson($this->hoursUrl($facility), ['hours' => [
            ['weekday' => 3, 'open_time' => '10:00', 'close_time' => '20:30'],
            ['weekday' => 1, 'open_time' => '08:00', 'close_time' => '22:00'],
        ]])
            ->assertOk()
            ->assertJsonPath('message', __('admin_hours.hours_updated', [], 'ar'))
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.weekday', 1)
            ->assertJsonPath('data.1.close_time', '20:30');

        $this->assertSame(2, OperatingHour::query()->where('facility_id', $facility->id)->count());
        $this->assertDatabaseMissing('operating_hours', ['facility_id' => $facility->id, 'weekday' => 0]);
        $this->assertDatabaseHas('operating_hours', ['facility_id' => $other->id, 'weekday' => 2]);

        $log = AuditLog::query()->where('action', 'operating_hours.updated')->firstOrFail();
        $this->assertSame('facility', $log->entity_type);
        $this->assertSame($facility->id, $log->entity_id);
        $this->assertCount(2, $log->before['hours']);
        $this->assertSame(['weekday' => 1, 'open_time' => '08:00', 'close_time' => '22:00'], $log->after['hours'][0]);
    }

    public function test_empty_hours_list_closes_every_day(): void
    {
        $this->actingAsHoursManager();
        $facility = Facility::factory()->create();
        OperatingHour::factory()->create(['facility_id' => $facility->id, 'weekday' => 4]);

        $this->putJson($this->hoursUrl($facility), ['hours' => []])->assertOk()->assertJsonCount(0, 'data');

        $this->assertSame(0, OperatingHour::query()->where('facility_id', $facility->id)->count());
    }

    public function test_hours_validation_errors_in_arabic_and_english(): void
    {
        $this->actingAsHoursManager();
        $facility = Facility::factory()->create();
        $day = ['weekday' => 1, 'open_time' => '08:00', 'close_time' => '22:00'];

        $cases = [
            [['hours' => [['weekday' => 1, 'open_time' => '22:00', 'close_time' => '08:00']]], 'hours.0.close_time', 'close_after_open'],
            [['hours' => [['weekday' => 1, 'open_time' => '08:00', 'close_time' => '08:00']]], 'hours.0.close_time', 'close_after_open'],
            [['hours' => [['weekday' => 1, 'open_time' => '08:00', 'close_time' => '00:00']]], 'hours.0.close_time', 'close_after_open'],
            [['hours' => [$day, $day]], 'hours.0.weekday', 'weekday_duplicate'],
            [['hours' => [[...$day, 'weekday' => 7]]], 'hours.0.weekday', 'weekday_invalid'],
            [['hours' => [[...$day, 'weekday' => 'x']]], 'hours.0.weekday', 'weekday_invalid'],
            [['hours' => [[...$day, 'open_time' => '8am']]], 'hours.0.open_time', 'time_invalid'],
            [['hours' => [[...$day, 'close_time' => null]]], 'hours.0.close_time', 'time_invalid'],
            [[], 'hours', 'hours_invalid'],
            [['hours' => 'x'], 'hours', 'hours_invalid'],
            [['hours' => array_map(fn (int $weekday) => [...$day, 'weekday' => $weekday % 7], range(0, 7))], 'hours', 'hours_invalid'],
        ];

        foreach ($cases as [$payload, $field, $key]) {
            $this->putJson($this->hoursUrl($facility), $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$field]);
            $this->assertSame(0, OperatingHour::query()->count());
        }

        $this->putJson($this->hoursUrl($facility), $cases[0][0])
            ->assertJsonValidationErrors(['hours.0.close_time' => __('admin_hours.validation.close_after_open', [], 'ar')]);
        $this->withHeader('Accept-Language', 'en')
            ->putJson($this->hoursUrl($facility), $cases[0][0])
            ->assertJsonValidationErrors(['hours.0.close_time' => 'The closing time must be later than the opening time on the same day.']);
    }

    public function test_hours_for_missing_facility_returns_404(): void
    {
        $this->actingAsHoursManager();

        $this->getJson('/api/v1/admin/facilities/999/operating-hours')->assertNotFound();
        $this->putJson('/api/v1/admin/facilities/999/operating-hours', ['hours' => []])->assertNotFound();
    }

    public function test_closure_store_converts_local_time_to_utc_and_back(): void
    {
        $staff = $this->actingAsHoursManager();
        $facility = Facility::factory()->create();

        $response = $this->postJson('/api/v1/admin/closures', [
            'facility_id' => $facility->id,
            'starts_at' => '2026-11-01 10:00',
            'ends_at' => '2026-11-01 14:30',
            'reason_ar' => 'صيانة',
        ])
            ->assertCreated()
            ->assertJsonPath('message', __('admin_hours.closure_created', [], 'ar'))
            ->assertJsonPath('data.facility_id', $facility->id)
            ->assertJsonPath('data.space_id', null)
            ->assertJsonPath('data.facility.id', $facility->id)
            ->assertJsonPath('data.reason_en', null)
            ->assertJsonPath('data.created_by_staff_id', $staff->id);

        $this->assertDatabaseHas('closures', ['id' => $response->json('data.id'), 'starts_at' => '2026-11-01 07:00:00', 'ends_at' => '2026-11-01 11:30:00']);

        $offset = CarbonImmutable::parse('2026-11-01 10:00', 'Asia/Damascus')->format('P');
        $response->assertJsonPath('data.starts_at', "2026-11-01T10:00:00{$offset}")
            ->assertJsonPath('data.ends_at', "2026-11-01T14:30:00{$offset}");

        $this->assertTrue(AuditLog::query()->where('action', 'closure.created')->exists());
    }

    public function test_closure_for_a_space_and_show(): void
    {
        $this->actingAsHoursManager();
        $space = Space::factory()->create();

        $id = $this->postJson('/api/v1/admin/closures', [
            'space_id' => $space->id,
            'starts_at' => '2026-11-01 10:00',
            'ends_at' => '2026-11-02 10:00',
        ])->assertCreated()->assertJsonPath('data.space.id', $space->id)->json('data.id');

        $this->getJson("/api/v1/admin/closures/{$id}")->assertOk()->assertJsonPath('data.space_id', $space->id);
        $this->getJson('/api/v1/admin/closures/999')->assertNotFound();
    }

    public function test_closure_target_rules_on_store(): void
    {
        $this->actingAsHoursManager();
        $facility = Facility::factory()->create();
        $space = Space::factory()->create();
        $times = ['starts_at' => '2026-11-01 10:00', 'ends_at' => '2026-11-01 12:00'];

        $this->postJson('/api/v1/admin/closures', [...$times, 'facility_id' => $facility->id, 'space_id' => $space->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['facility_id' => __('admin_hours.validation.target_exclusive', [], 'ar')]);

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/closures', $times)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['facility_id' => 'A closure needs either a facility or a space.', 'space_id' => 'A closure needs either a facility or a space.']);

        $this->postJson('/api/v1/admin/closures', [...$times, 'facility_id' => 999])->assertUnprocessable()->assertJsonValidationErrors(['facility_id']);
        $this->postJson('/api/v1/admin/closures', [...$times, 'space_id' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors(['space_id']);
        $this->assertSame(0, Closure::query()->count());
    }

    public function test_closure_store_validation(): void
    {
        $this->actingAsHoursManager();
        $facility = Facility::factory()->create();
        $base = ['facility_id' => $facility->id, 'starts_at' => '2026-11-01 10:00', 'ends_at' => '2026-11-01 12:00'];

        $this->postJson('/api/v1/admin/closures', [...$base, 'ends_at' => '2026-11-01 09:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at' => __('admin_hours.validation.ends_after_starts', [], 'ar')]);
        $this->postJson('/api/v1/admin/closures', [...$base, 'ends_at' => '2026-11-01 10:00'])->assertUnprocessable()->assertJsonValidationErrors(['ends_at']);
        $this->postJson('/api/v1/admin/closures', [...$base, 'starts_at' => '2026-11-01'])->assertUnprocessable()->assertJsonValidationErrors(['starts_at']);
        $this->postJson('/api/v1/admin/closures', [...$base, 'ends_at' => null])->assertUnprocessable()->assertJsonValidationErrors(['ends_at']);
        $this->postJson('/api/v1/admin/closures', [...$base, 'reason_ar' => str_repeat('a', 256)])->assertUnprocessable()->assertJsonValidationErrors(['reason_ar']);
        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/closures', [...$base, 'reason_en' => ['x']])
            ->assertJsonValidationErrors(['reason_en' => 'The English reason must be text of at most 255 characters.']);
    }

    public function test_closure_update_merges_with_current_values_and_audits(): void
    {
        $this->actingAsHoursManager();
        $facility = Facility::factory()->create();
        $space = Space::factory()->create();
        $closure = Closure::factory()->create([
            'facility_id' => $facility->id,
            'starts_at' => '2026-11-01 07:00:00',
            'ends_at' => '2026-11-01 09:00:00',
        ]);

        $this->putJson("/api/v1/admin/closures/{$closure->id}", ['ends_at' => '2026-11-01 10:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at' => __('admin_hours.validation.ends_after_starts', [], 'ar')]);

        $this->putJson("/api/v1/admin/closures/{$closure->id}", ['starts_at' => '2026-11-01 13:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at']);

        $this->putJson("/api/v1/admin/closures/{$closure->id}", ['space_id' => $space->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['space_id']);

        $this->putJson("/api/v1/admin/closures/{$closure->id}", ['facility_id' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['facility_id']);

        $this->putJson("/api/v1/admin/closures/{$closure->id}", ['ends_at' => '2026-11-01 15:00', 'reason_en' => 'Works'])
            ->assertOk()
            ->assertJsonPath('message', __('admin_hours.closure_updated', [], 'ar'))
            ->assertJsonPath('data.reason_en', 'Works');
        $this->assertDatabaseHas('closures', ['id' => $closure->id, 'ends_at' => '2026-11-01 12:00:00', 'starts_at' => '2026-11-01 07:00:00']);

        $this->putJson("/api/v1/admin/closures/{$closure->id}", ['facility_id' => null, 'space_id' => $space->id])
            ->assertOk()
            ->assertJsonPath('data.facility_id', null)
            ->assertJsonPath('data.space_id', $space->id);

        $this->assertSame(2, AuditLog::query()->where('action', 'closure.updated')->count());
        $this->putJson('/api/v1/admin/closures/999', ['reason_en' => 'x'])->assertNotFound();
    }

    public function test_closure_destroy_hard_deletes_and_audits(): void
    {
        $this->actingAsHoursManager();
        $closure = Closure::factory()->create(['facility_id' => Facility::factory()->create()->id]);

        $this->deleteJson("/api/v1/admin/closures/{$closure->id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_hours.closure_deleted', [], 'ar'));

        $this->assertDatabaseMissing('closures', ['id' => $closure->id]);
        $this->assertTrue(AuditLog::query()->where('action', 'closure.deleted')->exists());
        $this->deleteJson("/api/v1/admin/closures/{$closure->id}")->assertNotFound();
    }

    public function test_closure_index_filters_and_pagination(): void
    {
        $this->actingAsHoursManager();
        $facility = Facility::factory()->create();
        $space = Space::factory()->create();

        $make = fn (array $attributes) => Closure::factory()->create($attributes);
        $before = $make(['facility_id' => $facility->id, 'starts_at' => '2026-10-30 10:00:00', 'ends_at' => '2026-10-30 12:00:00']);
        $inside = $make(['facility_id' => $facility->id, 'starts_at' => '2026-11-02 10:00:00', 'ends_at' => '2026-11-02 12:00:00']);
        $straddling = $make(['space_id' => $space->id, 'starts_at' => '2026-10-31 20:00:00', 'ends_at' => '2026-11-01 06:00:00']);
        $after = $make(['space_id' => $space->id, 'starts_at' => '2026-11-10 10:00:00', 'ends_at' => '2026-11-10 12:00:00']);

        $ids = fn ($response) => collect($response->json('data'))->pluck('id')->all();

        $this->assertSame(
            [$after->id, $inside->id, $straddling->id, $before->id],
            $ids($this->getJson('/api/v1/admin/closures')->assertOk()->assertJsonPath('meta.per_page', 15)),
        );
        $this->assertSame(
            [$inside->id, $straddling->id],
            $ids($this->getJson('/api/v1/admin/closures?from=2026-11-01&to=2026-11-03')),
        );
        $this->assertSame([$inside->id, $straddling->id], $ids($this->getJson('/api/v1/admin/closures?from=2026-11-01&to=2026-11-02')));
        $this->assertSame([$after->id], $ids($this->getJson('/api/v1/admin/closures?from=2026-11-05')));
        $this->assertSame([$inside->id, $straddling->id, $before->id], $ids($this->getJson('/api/v1/admin/closures?to=2026-11-02')));
        $this->assertSame([$inside->id, $before->id], $ids($this->getJson("/api/v1/admin/closures?facility_id={$facility->id}")));
        $this->assertSame([$after->id, $straddling->id], $ids($this->getJson("/api/v1/admin/closures?space_id={$space->id}")));

        $this->travelTo('2026-11-03 00:00:00');
        $this->assertSame([$after->id], $ids($this->getJson('/api/v1/admin/closures?upcoming=1')));

        $this->getJson('/api/v1/admin/closures?per_page=2')->assertJsonCount(2, 'data');
    }

    public function test_closure_index_validation(): void
    {
        $this->actingAsHoursManager();

        $this->getJson('/api/v1/admin/closures?from=01-11-2026')->assertUnprocessable()->assertJsonValidationErrors(['from']);
        $this->getJson('/api/v1/admin/closures?from=2026-11-05&to=2026-11-01')->assertUnprocessable()->assertJsonValidationErrors(['to']);
        $this->getJson('/api/v1/admin/closures?per_page=101')->assertUnprocessable()->assertJsonValidationErrors(['per_page']);
        $this->getJson('/api/v1/admin/closures?upcoming=maybe')->assertUnprocessable()->assertJsonValidationErrors(['upcoming']);
        $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/admin/closures?per_page=0')
            ->assertJsonValidationErrors(['per_page' => 'The page size must be a whole number between 1 and 100.']);
    }
}
