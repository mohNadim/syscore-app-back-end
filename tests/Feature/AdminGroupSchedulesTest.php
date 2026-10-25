<?php

namespace Tests\Feature;

use App\Models\Academy;
use App\Models\AcademyGroup;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\BookableUnit;
use App\Models\Closure;
use App\Models\Coach;
use App\Models\Facility;
use App\Models\GroupSchedule;
use App\Models\OperatingHour;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Space;
use App\Models\SpaceReservation;
use App\Models\Sport;
use App\Models\Staff;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Academies\TrainingSessionGenerator;
use App\Services\Reservations\SpaceReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminGroupSchedulesTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facility;

    private Space $spaceA;

    private Space $spaceB;

    private BookableUnit $fullUnit;

    private BookableUnit $halfUnit;

    private AcademyGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');

        // Monday 2026-10-26 11:00 in Damascus (UTC+3).
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));

        $sport = Sport::factory()->create();
        $this->facility = Facility::factory()->create();
        $this->spaceA = Space::factory()->create(['facility_id' => $this->facility->id]);
        $this->spaceB = Space::factory()->create(['facility_id' => $this->facility->id]);

        $this->fullUnit = BookableUnit::factory()->create(['facility_id' => $this->facility->id, 'code' => 'PITCH_FULL']);
        $this->halfUnit = BookableUnit::factory()->create(['facility_id' => $this->facility->id, 'code' => 'PITCH_A']);
        $this->fullUnit->spaces()->attach([$this->spaceA->id, $this->spaceB->id]);
        $this->halfUnit->spaces()->attach([$this->spaceA->id]);
        $this->fullUnit->sports()->attach($sport->id);
        $this->halfUnit->sports()->attach($sport->id);

        foreach (range(0, 6) as $weekday) {
            OperatingHour::factory()->create(['facility_id' => $this->facility->id, 'weekday' => $weekday, 'open_time' => '08:00:00', 'close_time' => '22:00:00']);
        }

        $academy = Academy::factory()->create(['sport_id' => $sport->id]);
        $this->group = AcademyGroup::factory()->create(['academy_id' => $academy->id, 'coach_id' => Coach::factory()->create()->id]);
    }

    /**
     * @param  list<string>  $codes
     */
    private function staffWithPermissions(array $codes): Staff
    {
        $role = Role::factory()->create(['name_ar' => 'دور '.fake()->unique()->numerify('####'), 'name_en' => 'Role']);

        foreach ($codes as $code) {
            $permission = Permission::query()->firstOrCreate(['code' => $code], ['description' => $code]);
            $role->permissions()->attach($permission);
        }

        return Staff::factory()->create(['role_id' => $role->id]);
    }

    private function actingAsAcademiesManager(): Staff
    {
        $staff = $this->staffWithPermissions(['academies.manage']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    /**
     * Tuesday 16:00-17:30 local = 13:00-14:30 UTC on the full pitch.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'unit_id' => $this->fullUnit->id,
            'weekday' => 2,
            'start_time' => '16:00',
            'end_time' => '17:30',
            'valid_from' => '2026-10-26',
        ], $overrides);
    }

    private function url(?AcademyGroup $group = null): string
    {
        return '/api/v1/admin/groups/'.($group ?? $this->group)->id.'/schedules';
    }

    private function reserveSpace(Space $space, string $startsUtc, string $endsUtc, bool $active = true): SpaceReservation
    {
        return SpaceReservation::factory()->create([
            'space_id' => $space->id,
            'starts_at' => $startsUtc,
            'ends_at' => $endsUtc,
            'is_active' => $active,
        ]);
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $schedule = GroupSchedule::factory()->create(['group_id' => $this->group->id, 'unit_id' => $this->fullUnit->id]);

        $endpoints = [
            ['getJson', $this->url()],
            ['postJson', $this->url()],
            ['getJson', "/api/v1/admin/schedules/{$schedule->id}"],
            ['putJson', "/api/v1/admin/schedules/{$schedule->id}"],
            ['deleteJson', "/api/v1/admin/schedules/{$schedule->id}"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertUnauthorized();
        }

        Sanctum::actingAs(User::factory()->create());
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }

        Sanctum::actingAs($this->staffWithPermissions(['coaches.manage']));
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    public function test_store_generates_sessions_with_utc_times_and_reservations_for_every_space(): void
    {
        $this->actingAsAcademiesManager();

        $response = $this->postJson($this->url(), $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', __('admin_schedules.schedule_created', [], 'ar'))
            ->assertJsonPath('data.weekday', 2)
            ->assertJsonPath('data.start_time', '16:00')
            ->assertJsonPath('data.unit.id', $this->fullUnit->id)
            ->assertJsonPath('generated_sessions', 4)
            ->assertJsonPath('skipped_closed_dates', []);

        $scheduleId = $response->json('data.id');
        $sessions = TrainingSession::query()->where('schedule_id', $scheduleId)->orderBy('starts_at')->get();

        $this->assertSame(
            ['2026-10-27 13:00:00', '2026-11-03 13:00:00', '2026-11-10 13:00:00', '2026-11-17 13:00:00'],
            $sessions->map(fn ($session) => $session->starts_at->format('Y-m-d H:i:s'))->all(),
        );
        $this->assertSame('2026-10-27 14:30:00', $sessions->first()->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame($this->group->coach_id, $sessions->first()->coach_id);
        $this->assertSame('scheduled', $sessions->first()->status);

        $this->assertSame(8, SpaceReservation::query()->whereIn('session_id', $sessions->modelKeys())->where('is_active', true)->count());
        $this->assertEqualsCanonicalizing(
            [$this->spaceA->id, $this->spaceB->id],
            SpaceReservation::query()->where('session_id', $sessions->first()->id)->pluck('space_id')->all(),
        );
        $this->assertTrue(AuditLog::query()->where('action', 'group_schedule.created')->where('entity_id', $scheduleId)->exists());
    }

    public function test_weekday_is_computed_on_the_local_date(): void
    {
        $this->actingAsAcademiesManager();

        // Wednesday 00:30 local is Tuesday 21:30 UTC: the session must land on the local Wednesday.
        $this->postJson($this->url(), $this->payload(['weekday' => 3, 'start_time' => '08:30', 'end_time' => '09:30']))->assertCreated();
        $this->assertSame('2026-10-28 05:30:00', TrainingSession::query()->orderBy('starts_at')->first()->starts_at->format('Y-m-d H:i:s'));
    }

    public function test_conflict_on_shared_space_between_half_and_full_unit_returns_409_and_persists_nothing(): void
    {
        $this->actingAsAcademiesManager();
        $this->reserveSpace($this->spaceA, '2026-11-10 14:00:00', '2026-11-10 15:00:00');

        $this->postJson($this->url(), $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('conflicts.0.date', '2026-11-10')
            ->assertJsonCount(1, 'conflicts')
            ->assertJsonStructure(['message', 'conflicts' => [['date', 'starts_at', 'ends_at']]]);

        $this->assertSame(0, GroupSchedule::query()->count());
        $this->assertSame(0, TrainingSession::query()->count());
        $this->assertSame(1, SpaceReservation::query()->count());
    }

    public function test_full_pitch_reservation_blocks_a_half_pitch_schedule(): void
    {
        $this->actingAsAcademiesManager();
        $this->reserveSpace($this->spaceA, '2026-10-27 13:30:00', '2026-10-27 14:00:00');
        $this->reserveSpace($this->spaceB, '2026-10-27 13:30:00', '2026-10-27 14:00:00');

        $this->postJson($this->url(), $this->payload(['unit_id' => $this->halfUnit->id]))
            ->assertStatus(409)
            ->assertJsonPath('conflicts.0.date', '2026-10-27');

        $this->assertSame(0, TrainingSession::query()->count());
    }

    public function test_touching_edges_inactive_reservations_and_other_spaces_do_not_conflict(): void
    {
        $this->actingAsAcademiesManager();
        $this->reserveSpace($this->spaceA, '2026-10-27 12:00:00', '2026-10-27 13:00:00');
        $this->reserveSpace($this->spaceB, '2026-10-27 14:30:00', '2026-10-27 15:30:00');
        $this->reserveSpace($this->spaceA, '2026-11-03 13:00:00', '2026-11-03 14:30:00', active: false);

        $this->postJson($this->url(), $this->payload())->assertCreated()->assertJsonPath('generated_sessions', 4);
    }

    public function test_half_pitch_schedule_does_not_conflict_with_the_other_half(): void
    {
        $this->actingAsAcademiesManager();
        $this->reserveSpace($this->spaceB, '2026-10-27 13:00:00', '2026-10-27 14:30:00');

        $this->postJson($this->url(), $this->payload(['unit_id' => $this->halfUnit->id]))
            ->assertCreated()
            ->assertJsonPath('generated_sessions', 4);
    }

    public function test_closed_dates_are_skipped_and_reported(): void
    {
        $this->actingAsAcademiesManager();
        Closure::factory()->create([
            'facility_id' => $this->facility->id,
            'starts_at' => '2026-11-03 00:00:00',
            'ends_at' => '2026-11-03 23:00:00',
        ]);
        Closure::factory()->create([
            'space_id' => $this->spaceB->id,
            'starts_at' => '2026-11-10 13:30:00',
            'ends_at' => '2026-11-10 20:00:00',
        ]);

        $this->postJson($this->url(), $this->payload())
            ->assertCreated()
            ->assertJsonPath('generated_sessions', 2)
            ->assertJsonPath('skipped_closed_dates', ['2026-11-03', '2026-11-10']);
    }

    public function test_generation_is_idempotent(): void
    {
        $this->actingAsAcademiesManager();
        $scheduleId = $this->postJson($this->url(), $this->payload())->assertCreated()->json('data.id');

        $schedule = GroupSchedule::findOrFail($scheduleId);
        $result = app(TrainingSessionGenerator::class)->generate($schedule, CarbonImmutable::parse('2026-10-26'), CarbonImmutable::parse('2026-11-23'));

        $this->assertSame([], $result->created);
        $this->assertSame(4, TrainingSession::query()->count());
        $this->assertSame(8, SpaceReservation::query()->count());
    }

    public function test_generation_respects_validity_window(): void
    {
        $this->actingAsAcademiesManager();

        $this->postJson($this->url(), $this->payload(['valid_from' => '2026-11-03', 'valid_to' => '2026-11-10']))
            ->assertCreated()
            ->assertJsonPath('generated_sessions', 2);
    }

    public function test_update_regenerates_future_sessions_and_keeps_changed_ones(): void
    {
        $this->actingAsAcademiesManager();
        $scheduleId = $this->postJson($this->url(), $this->payload())->assertCreated()->json('data.id');

        $kept = TrainingSession::query()->where('schedule_id', $scheduleId)->orderBy('starts_at')->first();
        $kept->update(['status' => 'changed', 'original_starts_at' => $kept->starts_at]);
        $oldIds = TrainingSession::query()->where('schedule_id', $scheduleId)->where('id', '!=', $kept->id)->pluck('id');

        $this->putJson("/api/v1/admin/schedules/{$scheduleId}", ['start_time' => '18:00', 'end_time' => '19:00'])
            ->assertOk()
            ->assertJsonPath('message', __('admin_schedules.schedule_updated', [], 'ar'))
            ->assertJsonPath('data.start_time', '18:00')
            ->assertJsonPath('generated_sessions', 4);

        $this->assertSame(0, TrainingSession::query()->whereKey($oldIds)->count());
        $this->assertSame(0, SpaceReservation::query()->whereIn('session_id', $oldIds)->count());
        $this->assertSame(5, TrainingSession::query()->where('schedule_id', $scheduleId)->count());
        $this->assertSame('changed', $kept->fresh()->status);
        $this->assertTrue(SpaceReservation::query()->where('session_id', $kept->id)->where('is_active', true)->exists());
        $this->assertSame('2026-11-03 15:00:00', TrainingSession::query()->where('schedule_id', $scheduleId)->where('starts_at', '>', '2026-10-28')->orderBy('starts_at')->first()->starts_at->format('Y-m-d H:i:s'));
        $this->assertTrue(AuditLog::query()->where('action', 'group_schedule.updated')->where('entity_id', $scheduleId)->exists());
    }

    public function test_update_conflict_returns_409_and_changes_nothing(): void
    {
        $this->actingAsAcademiesManager();
        $scheduleId = $this->postJson($this->url(), $this->payload())->assertCreated()->json('data.id');
        $this->reserveSpace($this->spaceA, '2026-11-10 15:00:00', '2026-11-10 16:00:00');

        $before = TrainingSession::query()->where('schedule_id', $scheduleId)->orderBy('id')->pluck('id', 'starts_at')->all();

        $this->putJson("/api/v1/admin/schedules/{$scheduleId}", ['start_time' => '18:00', 'end_time' => '19:00'])
            ->assertStatus(409)
            ->assertJsonPath('conflicts.0.date', '2026-11-10');

        $this->assertSame('16:00:00', GroupSchedule::findOrFail($scheduleId)->start_time);
        $this->assertSame($before, TrainingSession::query()->where('schedule_id', $scheduleId)->orderBy('id')->pluck('id', 'starts_at')->all());
        $this->assertSame(9, SpaceReservation::query()->count());
    }

    public function test_destroy_deletes_schedule_when_no_sessions_remain(): void
    {
        $this->actingAsAcademiesManager();
        $scheduleId = $this->postJson($this->url(), $this->payload())->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/admin/schedules/{$scheduleId}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_schedules.schedule_deleted', [], 'ar'));

        $this->assertSame(0, GroupSchedule::query()->count());
        $this->assertSame(0, TrainingSession::query()->count());
        $this->assertSame(0, SpaceReservation::query()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'group_schedule.deleted')->exists());
    }

    public function test_destroy_ends_the_schedule_when_past_or_attended_sessions_remain(): void
    {
        $this->actingAsAcademiesManager();
        $scheduleId = $this->postJson($this->url(), $this->payload())->assertCreated()->json('data.id');

        $past = TrainingSession::factory()->create([
            'group_id' => $this->group->id,
            'schedule_id' => $scheduleId,
            'unit_id' => $this->fullUnit->id,
            'starts_at' => '2026-10-20 13:00:00',
            'ends_at' => '2026-10-20 14:30:00',
        ]);
        $attended = TrainingSession::query()->where('schedule_id', $scheduleId)->where('starts_at', '>', now())->orderBy('starts_at')->first();
        Attendance::factory()->create(['session_id' => $attended->id]);

        $this->deleteJson("/api/v1/admin/schedules/{$scheduleId}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_schedules.schedule_ended', [], 'ar'))
            ->assertJsonPath('data.valid_to', '2026-10-25');

        $this->assertSame(2, TrainingSession::query()->where('schedule_id', $scheduleId)->count());
        $this->assertNotNull($past->fresh());
        $this->assertNotNull($attended->fresh());
        $this->assertSame(2, SpaceReservation::query()->where('session_id', $attended->id)->count());
        $this->assertTrue(AuditLog::query()->where('action', 'group_schedule.ended')->exists());
    }

    public function test_index_and_show(): void
    {
        $this->actingAsAcademiesManager();
        $scheduleId = $this->postJson($this->url(), $this->payload())->json('data.id');

        $this->getJson($this->url())->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.unit.id', $this->fullUnit->id);
        $this->getJson("/api/v1/admin/schedules/{$scheduleId}")->assertOk()->assertJsonPath('data.id', $scheduleId);
        $this->getJson('/api/v1/admin/schedules/999999')->assertNotFound();
        $this->getJson('/api/v1/admin/groups/999999/schedules')->assertNotFound();
    }

    public function test_validation_errors_in_arabic(): void
    {
        $this->actingAsAcademiesManager();

        $this->postJson($this->url(), [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.unit_id.0', __('admin_schedules.validation.unit_id_required', [], 'ar'))
            ->assertJsonPath('errors.weekday.0', __('admin_schedules.validation.weekday_required', [], 'ar'))
            ->assertJsonPath('errors.start_time.0', __('admin_schedules.validation.start_time_required', [], 'ar'))
            ->assertJsonPath('errors.end_time.0', __('admin_schedules.validation.end_time_required', [], 'ar'))
            ->assertJsonPath('errors.valid_from.0', __('admin_schedules.validation.valid_from_required', [], 'ar'));

        $this->postJson($this->url(), $this->payload(['weekday' => 7, 'start_time' => '25:00', 'valid_from' => 'abc', 'valid_to' => 'x', 'unit_id' => 'z']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.weekday.0', __('admin_schedules.validation.weekday_invalid', [], 'ar'))
            ->assertJsonPath('errors.start_time.0', __('admin_schedules.validation.time_invalid', [], 'ar'))
            ->assertJsonPath('errors.valid_from.0', __('admin_schedules.validation.date_invalid', [], 'ar'))
            ->assertJsonPath('errors.valid_to.0', __('admin_schedules.validation.date_invalid', [], 'ar'))
            ->assertJsonPath('errors.unit_id.0', __('admin_schedules.validation.unit_id_invalid', [], 'ar'));
    }

    public function test_business_rule_errors_in_english(): void
    {
        $this->actingAsAcademiesManager();
        $this->withHeader('Accept-Language', 'en');

        $otherSport = Sport::factory()->create();
        $mismatch = BookableUnit::factory()->create(['facility_id' => $this->facility->id]);
        $mismatch->sports()->attach($otherSport->id);

        $this->postJson($this->url(), $this->payload(['unit_id' => $mismatch->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.unit_id.0', 'The unit does not support the sport of this academy.');

        $this->postJson($this->url(), $this->payload(['start_time' => '17:00', 'end_time' => '16:00']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.end_time.0', 'The end time must be after the start time.');

        $this->postJson($this->url(), $this->payload(['start_time' => '06:00', 'end_time' => '07:00']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.start_time.0', 'The time range must be inside the facility operating hours for that weekday.');

        $this->postJson($this->url(), $this->payload(['start_time' => '21:00', 'end_time' => '23:00']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');

        $this->postJson($this->url(), $this->payload(['valid_from' => '2026-10-25']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.valid_from.0', 'The start date cannot be in the past.');

        $this->postJson($this->url(), $this->payload(['valid_to' => '2026-10-20']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.valid_to.0', 'The end date cannot be before the start date.');

        $this->assertSame(0, GroupSchedule::query()->count());
    }

    public function test_day_without_operating_hours_and_inactive_units_are_rejected(): void
    {
        $this->actingAsAcademiesManager();
        OperatingHour::query()->where('weekday', 2)->delete();

        $this->postJson($this->url(), $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.start_time.0', __('admin_schedules.validation.outside_operating_hours', [], 'ar'));

        $this->halfUnit->update(['is_active' => false]);
        $this->postJson($this->url(), $this->payload(['unit_id' => $this->halfUnit->id, 'weekday' => 3]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.unit_id.0', __('admin_schedules.validation.unit_inactive', [], 'ar'));
    }

    public function test_update_validates_against_stored_values(): void
    {
        $this->actingAsAcademiesManager();
        $schedule = GroupSchedule::factory()->create([
            'group_id' => $this->group->id,
            'unit_id' => $this->fullUnit->id,
            'weekday' => 2,
            'start_time' => '16:00:00',
            'end_time' => '17:00:00',
            'valid_from' => '2026-10-01',
        ]);

        $this->putJson("/api/v1/admin/schedules/{$schedule->id}", ['end_time' => '15:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_time');

        $this->putJson("/api/v1/admin/schedules/{$schedule->id}", ['valid_to' => '2026-09-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('valid_to');

        // An old valid_from is allowed on update.
        $this->putJson("/api/v1/admin/schedules/{$schedule->id}", ['end_time' => '17:30'])->assertOk();
    }

    public function test_service_locks_spaces_for_update_before_checking_conflicts(): void
    {
        $service = app(SpaceReservationService::class);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        DB::transaction(function () use ($service): void {
            $service->lockSpaces([$this->spaceB->id, $this->spaceA->id]);
            $service->conflicts([$this->spaceA->id], CarbonImmutable::now(), CarbonImmutable::now()->addHour());
        });

        $lockIndex = collect($queries)->search(fn (string $sql) => str_contains($sql, 'from `spaces`') && str_contains($sql, 'for update') && str_contains($sql, 'order by `id`'));
        $conflictIndex = collect($queries)->search(fn (string $sql) => str_contains($sql, 'from `space_reservations`'));

        $this->assertNotFalse($lockIndex);
        $this->assertNotFalse($conflictIndex);
        $this->assertLessThan($conflictIndex, $lockIndex);
    }

    public function test_generator_locks_spaces_before_conflict_checks(): void
    {
        $schedule = GroupSchedule::factory()->create([
            'group_id' => $this->group->id,
            'unit_id' => $this->fullUnit->id,
            'weekday' => 2,
            'valid_from' => '2026-10-26',
        ]);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        app(TrainingSessionGenerator::class)->generate($schedule, CarbonImmutable::parse('2026-10-26'), CarbonImmutable::parse('2026-11-23'));

        $lockIndex = collect($queries)->search(fn (string $sql) => str_contains($sql, 'for update'));
        $conflictIndex = collect($queries)->search(fn (string $sql) => str_contains($sql, 'from `space_reservations`'));

        $this->assertNotFalse($lockIndex);
        $this->assertLessThan($conflictIndex, $lockIndex);
    }
}
