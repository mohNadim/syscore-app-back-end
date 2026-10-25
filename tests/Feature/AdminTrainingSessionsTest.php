<?php

namespace Tests\Feature;

use App\Models\Academy;
use App\Models\AcademyGroup;
use App\Models\AuditLog;
use App\Models\BookableUnit;
use App\Models\Closure;
use App\Models\Coach;
use App\Models\Facility;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Space;
use App\Models\SpaceReservation;
use App\Models\Sport;
use App\Models\Staff;
use App\Models\TrainingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTrainingSessionsTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facility;

    private Space $spaceA;

    private Space $spaceB;

    private BookableUnit $fullUnit;

    private BookableUnit $halfUnit;

    private AcademyGroup $group;

    private Sport $sport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');

        // Monday 2026-10-26 11:00 in Damascus (UTC+3).
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));

        $this->sport = Sport::factory()->create();
        $this->facility = Facility::factory()->create();
        $this->spaceA = Space::factory()->create(['facility_id' => $this->facility->id]);
        $this->spaceB = Space::factory()->create(['facility_id' => $this->facility->id]);
        $this->fullUnit = BookableUnit::factory()->create(['facility_id' => $this->facility->id]);
        $this->halfUnit = BookableUnit::factory()->create(['facility_id' => $this->facility->id]);
        $this->fullUnit->spaces()->attach([$this->spaceA->id, $this->spaceB->id]);
        $this->halfUnit->spaces()->attach([$this->spaceA->id]);
        $this->fullUnit->sports()->attach($this->sport->id);
        $this->halfUnit->sports()->attach($this->sport->id);

        $academy = Academy::factory()->create(['sport_id' => $this->sport->id]);
        $this->group = AcademyGroup::factory()->create(['academy_id' => $academy->id]);
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

    private function actingAsSessionsManager(): Staff
    {
        $staff = $this->staffWithPermissions(['sessions.manage']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    /**
     * Create a session on the full unit (UTC times) with active reservations on both spaces.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeSession(string $startsUtc = '2026-10-27 13:00:00', string $endsUtc = '2026-10-27 14:30:00', array $overrides = []): TrainingSession
    {
        $session = TrainingSession::factory()->create(array_merge([
            'group_id' => $this->group->id,
            'unit_id' => $this->fullUnit->id,
            'starts_at' => $startsUtc,
            'ends_at' => $endsUtc,
        ], $overrides));

        foreach ($this->fullUnit->spaces as $space) {
            SpaceReservation::factory()->forSession()->create([
                'space_id' => $space->id,
                'session_id' => $session->id,
                'starts_at' => $startsUtc,
                'ends_at' => $endsUtc,
            ]);
        }

        return $session;
    }

    private function sessionUrl(TrainingSession $session): string
    {
        return "/api/v1/admin/training-sessions/{$session->id}";
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $session = $this->makeSession();

        $endpoints = [
            ['getJson', '/api/v1/admin/training-sessions'],
            ['getJson', $this->sessionUrl($session)],
            ['patchJson', $this->sessionUrl($session)],
            ['postJson', $this->sessionUrl($session).'/cancel'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertUnauthorized();
        }

        Sanctum::actingAs(User::factory()->create());
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }

        Sanctum::actingAs($this->staffWithPermissions(['academies.manage']));
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    public function test_index_filters_orders_and_paginates(): void
    {
        $this->actingAsSessionsManager();
        $coach = Coach::factory()->create();
        $second = $this->makeSession('2026-10-28 13:00:00', '2026-10-28 14:30:00', ['coach_id' => $coach->id]);
        $first = $this->makeSession('2026-10-27 13:00:00', '2026-10-27 14:30:00');
        $otherGroup = AcademyGroup::factory()->create();
        $other = TrainingSession::factory()->cancelled()->create([
            'group_id' => $otherGroup->id,
            'starts_at' => '2026-11-02 13:00:00',
            'ends_at' => '2026-11-02 14:00:00',
        ]);

        $this->getJson('/api/v1/admin/training-sessions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.2.id', $other->id)
            ->assertJsonPath('data.0.group.academy.id', $this->group->academy_id)
            ->assertJsonPath('data.0.starts_at', '2026-10-27T16:00:00+03:00')
            ->assertJsonPath('meta.total', 3);

        $this->getJson("/api/v1/admin/training-sessions?academy_id={$this->group->academy_id}")->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/admin/training-sessions?group_id={$otherGroup->id}")->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/training-sessions?unit_id={$this->fullUnit->id}")->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/admin/training-sessions?coach_id={$coach->id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id);
        $this->getJson('/api/v1/admin/training-sessions?status=cancelled')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);
        $this->getJson('/api/v1/admin/training-sessions?from=2026-10-28&to=2026-10-28')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id);
        $this->getJson('/api/v1/admin/training-sessions?per_page=1')->assertJsonCount(1, 'data')->assertJsonPath('meta.per_page', 1);
    }

    public function test_index_validation_messages_in_both_languages(): void
    {
        $this->actingAsSessionsManager();

        $this->getJson('/api/v1/admin/training-sessions?status=x&from=bad&per_page=0&group_id=a')
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', __('admin_sessions.validation.status_invalid', [], 'ar'))
            ->assertJsonPath('errors.from.0', __('admin_sessions.validation.date_invalid', [], 'ar'))
            ->assertJsonPath('errors.per_page.0', __('admin_sessions.validation.per_page_invalid', [], 'ar'))
            ->assertJsonPath('errors.group_id.0', __('admin_sessions.validation.group_id_invalid', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/admin/training-sessions?status=x')
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'The status must be one of: scheduled, changed, cancelled, done.');
    }

    public function test_show_returns_session_or_404(): void
    {
        $this->actingAsSessionsManager();
        $session = $this->makeSession();

        $this->getJson($this->sessionUrl($session))
            ->assertOk()
            ->assertJsonPath('data.id', $session->id)
            ->assertJsonPath('data.unit.id', $this->fullUnit->id)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.original_starts_at', null);

        $this->getJson('/api/v1/admin/training-sessions/999999')->assertNotFound();
    }

    public function test_reschedule_moves_reservations_and_marks_the_session_changed(): void
    {
        $this->actingAsSessionsManager();
        $session = $this->makeSession();

        $this->patchJson($this->sessionUrl($session), [
            'starts_at' => '2026-10-29 18:00',
            'ends_at' => '2026-10-29 19:30',
            'change_reason' => 'مباراة ودية',
        ])
            ->assertOk()
            ->assertJsonPath('message', __('admin_sessions.session_updated', [], 'ar'))
            ->assertJsonPath('data.status', 'changed')
            ->assertJsonPath('data.starts_at', '2026-10-29T18:00:00+03:00')
            ->assertJsonPath('data.original_starts_at', '2026-10-27T16:00:00+03:00')
            ->assertJsonPath('data.change_reason', 'مباراة ودية');

        $session->refresh();
        $this->assertSame('2026-10-29 15:00:00', $session->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-27 13:00:00', $session->original_starts_at->format('Y-m-d H:i:s'));
        $this->assertSame(2, SpaceReservation::query()->where('session_id', $session->id)->where('is_active', true)->where('starts_at', '2026-10-29 15:00:00')->count());
        $this->assertSame(2, SpaceReservation::query()->where('session_id', $session->id)->where('is_active', false)->where('starts_at', '2026-10-27 13:00:00')->count());
        $this->assertTrue(AuditLog::query()->where('action', 'training_session.updated')->where('entity_id', $session->id)->exists());

        // A second change keeps the first original time.
        $this->patchJson($this->sessionUrl($session), ['starts_at' => '2026-10-30 18:00', 'ends_at' => '2026-10-30 19:00', 'change_reason' => 'x'])->assertOk();
        $this->assertSame('2026-10-27 13:00:00', $session->fresh()->original_starts_at->format('Y-m-d H:i:s'));
    }

    public function test_reassign_unit_and_coach_swaps_reservations_to_the_new_unit_spaces(): void
    {
        $this->actingAsSessionsManager();
        $session = $this->makeSession();
        $coach = Coach::factory()->create();

        $this->patchJson($this->sessionUrl($session), ['unit_id' => $this->halfUnit->id, 'coach_id' => $coach->id, 'change_reason' => 'ملعب آخر'])
            ->assertOk()
            ->assertJsonPath('data.unit_id', $this->halfUnit->id)
            ->assertJsonPath('data.coach.id', $coach->id);

        $this->assertSame(1, SpaceReservation::query()->where('session_id', $session->id)->where('is_active', true)->count());
        $this->assertSame($this->spaceA->id, SpaceReservation::query()->where('session_id', $session->id)->where('is_active', true)->value('space_id'));

        $this->patchJson($this->sessionUrl($session), ['coach_id' => null, 'change_reason' => 'بدون مدرب'])
            ->assertOk()
            ->assertJsonPath('data.coach_id', null);
        $this->assertSame(1, SpaceReservation::query()->where('session_id', $session->id)->where('is_active', true)->count());
    }

    public function test_reschedule_conflicting_on_a_shared_space_returns_409_and_changes_nothing(): void
    {
        $this->actingAsSessionsManager();
        $session = $this->makeSession();
        SpaceReservation::factory()->create([
            'space_id' => $this->spaceB->id,
            'starts_at' => '2026-10-29 15:30:00',
            'ends_at' => '2026-10-29 16:30:00',
        ]);

        $this->patchJson($this->sessionUrl($session), ['starts_at' => '2026-10-29 18:00', 'ends_at' => '2026-10-29 19:30', 'change_reason' => 'x'])
            ->assertStatus(409)
            ->assertJsonPath('message', __('admin_sessions.slot_taken', [], 'ar'));

        $session->refresh();
        $this->assertSame('scheduled', $session->status);
        $this->assertSame('2026-10-27 13:00:00', $session->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame(2, SpaceReservation::query()->where('session_id', $session->id)->where('is_active', true)->count());
    }

    public function test_rescheduling_over_its_own_old_slot_and_touching_edges_is_allowed(): void
    {
        $this->actingAsSessionsManager();
        $session = $this->makeSession();
        SpaceReservation::factory()->create([
            'space_id' => $this->spaceA->id,
            'starts_at' => '2026-10-27 14:30:00',
            'ends_at' => '2026-10-27 15:30:00',
        ]);

        // Shift by 30 minutes: overlaps its own old reservation and touches the other booking.
        $this->patchJson($this->sessionUrl($session), ['starts_at' => '2026-10-27 16:30', 'ends_at' => '2026-10-27 17:30', 'change_reason' => 'x'])
            ->assertOk();
    }

    public function test_reschedule_into_a_closure_returns_422(): void
    {
        $this->actingAsSessionsManager();
        $session = $this->makeSession();
        Closure::factory()->create([
            'space_id' => $this->spaceB->id,
            'starts_at' => '2026-10-29 00:00:00',
            'ends_at' => '2026-10-29 23:00:00',
        ]);

        $this->patchJson($this->sessionUrl($session), ['starts_at' => '2026-10-29 18:00', 'ends_at' => '2026-10-29 19:00', 'change_reason' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', __('admin_sessions.closed', [], 'ar'));

        $this->assertSame('scheduled', $session->fresh()->status);
        $this->assertSame(2, SpaceReservation::query()->where('session_id', $session->id)->where('is_active', true)->count());
    }

    public function test_locked_sessions_cannot_be_changed_or_cancelled(): void
    {
        $this->actingAsSessionsManager();
        $cancelled = $this->makeSession('2026-10-28 13:00:00', '2026-10-28 14:00:00', ['status' => 'cancelled']);
        $done = $this->makeSession('2026-10-29 13:00:00', '2026-10-29 14:00:00', ['status' => 'done']);
        $started = $this->makeSession('2026-10-26 07:00:00', '2026-10-26 09:00:00');

        foreach ([$cancelled, $done, $started] as $session) {
            $this->patchJson($this->sessionUrl($session), ['coach_id' => null, 'change_reason' => 'x'])
                ->assertStatus(409)
                ->assertJsonPath('message', __('admin_sessions.session_locked', [], 'ar'));
            $this->postJson($this->sessionUrl($session).'/cancel', ['reason' => 'x'])
                ->assertStatus(409)
                ->assertJsonPath('message', __('admin_sessions.session_locked', [], 'ar'));
        }
    }

    public function test_update_validation_errors(): void
    {
        $this->actingAsSessionsManager();
        $session = $this->makeSession();
        $otherSportUnit = BookableUnit::factory()->create(['facility_id' => $this->facility->id]);
        $otherSportUnit->sports()->attach(Sport::factory()->create()->id);
        $inactive = BookableUnit::factory()->inactive()->create(['facility_id' => $this->facility->id]);

        $this->patchJson($this->sessionUrl($session), [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.change_reason.0', __('admin_sessions.validation.change_reason_required', [], 'ar'));

        $this->patchJson($this->sessionUrl($session), ['starts_at' => '2026-10-29 18:00', 'change_reason' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.ends_at.0', __('admin_sessions.validation.times_together', [], 'ar'));

        $this->patchJson($this->sessionUrl($session), ['starts_at' => 'abc', 'ends_at' => '2026-10-29 18:00', 'change_reason' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', __('admin_sessions.validation.datetime_invalid', [], 'ar'));

        $this->patchJson($this->sessionUrl($session), ['starts_at' => '2026-10-29 19:00', 'ends_at' => '2026-10-29 18:00', 'change_reason' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.ends_at.0', __('admin_sessions.validation.ends_before_starts', [], 'ar'));

        $this->patchJson($this->sessionUrl($session), ['starts_at' => '2026-10-20 18:00', 'ends_at' => '2026-10-20 19:00', 'change_reason' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', __('admin_sessions.validation.starts_in_past', [], 'ar'));

        $this->patchJson($this->sessionUrl($session), ['unit_id' => $otherSportUnit->id, 'change_reason' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.unit_id.0', __('admin_sessions.validation.unit_sport_mismatch', [], 'ar'));

        $this->patchJson($this->sessionUrl($session), ['unit_id' => $inactive->id, 'coach_id' => 999999, 'change_reason' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['coach_id', 'change_reason']);

        $this->withHeader('Accept-Language', 'en')
            ->patchJson($this->sessionUrl($session), ['unit_id' => $inactive->id, 'change_reason' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.unit_id.0', 'The unit and its facility must be active.');

        $this->assertSame('scheduled', $session->fresh()->status);
    }

    public function test_cancel_releases_reservations_and_audits(): void
    {
        $this->actingAsSessionsManager();
        $session = $this->makeSession();

        $this->postJson($this->sessionUrl($session).'/cancel', ['reason' => 'طقس سيئ'])
            ->assertOk()
            ->assertJsonPath('message', __('admin_sessions.session_cancelled', [], 'ar'))
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.change_reason', 'طقس سيئ');

        $this->assertSame(0, SpaceReservation::query()->where('session_id', $session->id)->where('is_active', true)->count());
        $this->assertSame(2, SpaceReservation::query()->where('session_id', $session->id)->where('is_active', false)->count());
        $this->assertTrue(AuditLog::query()->where('action', 'training_session.cancelled')->where('entity_id', $session->id)->exists());

        // A cancelled session cannot be cancelled again.
        $this->postJson($this->sessionUrl($session).'/cancel', ['reason' => 'again'])->assertStatus(409);
    }

    public function test_cancel_validation_in_both_languages(): void
    {
        $this->actingAsSessionsManager();
        $session = $this->makeSession();

        $this->postJson($this->sessionUrl($session).'/cancel', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.reason.0', __('admin_sessions.validation.reason_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson($this->sessionUrl($session).'/cancel', ['reason' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.reason.0', 'The cancellation reason must be text of at most 255 characters.');

        $this->assertSame('scheduled', $session->fresh()->status);
    }
}
