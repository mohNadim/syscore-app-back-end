<?php

namespace Tests\Feature;

use App\Models\Academy;
use App\Models\AcademyGroup;
use App\Models\Attendance;
use App\Models\Child;
use App\Models\Enrollment;
use App\Models\Staff;
use App\Models\TrainingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChildScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Child $child;

    private AcademyGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');

        // Monday 2026-10-26 11:00 in Damascus (UTC+3).
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));

        $this->user = Sanctum::actingAs(User::factory()->create());
        $this->child = Child::factory()->create(['user_id' => $this->user->id]);
        $this->group = AcademyGroup::factory()->create(['academy_id' => Academy::factory()->create()->id]);
        Enrollment::factory()->create(['child_id' => $this->child->id, 'group_id' => $this->group->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSession(string $startsUtc, array $overrides = []): TrainingSession
    {
        return TrainingSession::factory()->create(array_merge([
            'group_id' => $this->group->id,
            'starts_at' => $startsUtc,
            'ends_at' => CarbonImmutable::parse($startsUtc)->addMinutes(90),
        ], $overrides));
    }

    private function mark(TrainingSession $session, string $status, ?Child $child = null): Attendance
    {
        return Attendance::factory()->create([
            'session_id' => $session->id,
            'child_id' => ($child ?? $this->child)->id,
            'status' => $status,
            'marked_by_staff_id' => Staff::factory(),
        ]);
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $other = Child::factory()->create();
        $archived = Child::factory()->create(['user_id' => $this->user->id, 'is_active' => false]);

        foreach (['schedule', 'attendance'] as $endpoint) {
            $this->getJson("/api/v1/children/{$other->id}/{$endpoint}")->assertNotFound();
            $this->getJson("/api/v1/children/{$archived->id}/{$endpoint}")->assertNotFound();
            $this->getJson("/api/v1/children/999999/{$endpoint}")->assertNotFound();
        }

        $this->app['auth']->forgetGuards();

        foreach (['schedule', 'attendance'] as $endpoint) {
            $this->getJson("/api/v1/children/{$this->child->id}/{$endpoint}")->assertUnauthorized();
        }

        Sanctum::actingAs(Staff::factory()->create());

        foreach (['schedule', 'attendance'] as $endpoint) {
            $this->getJson("/api/v1/children/{$this->child->id}/{$endpoint}")->assertForbidden();
        }
    }

    public function test_schedule_defaults_to_the_next_two_weeks_of_enrolled_groups(): void
    {
        $today = $this->makeSession('2026-10-26 13:00:00');
        $cancelled = $this->makeSession('2026-10-28 13:00:00', ['status' => 'cancelled']);
        $this->makeSession('2026-10-25 13:00:00');
        $this->makeSession('2026-11-09 13:00:00');
        $this->makeSession('2026-10-27 13:00:00', ['group_id' => AcademyGroup::factory()->create()->id]);
        $this->mark($today, 'present');

        $response = $this->getJson("/api/v1/children/{$this->child->id}/schedule")->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $today->id)
            ->assertJsonPath('data.0.attendance_status', 'present')
            ->assertJsonPath('data.1.id', $cancelled->id)
            ->assertJsonPath('data.1.status', 'cancelled')
            ->assertJsonPath('data.1.attendance_status', null);
    }

    public function test_schedule_skips_groups_after_the_enrollment_ended_and_other_children_marks(): void
    {
        $session = $this->makeSession('2026-10-27 13:00:00');
        $this->mark($session, 'absent', Child::factory()->create());

        $this->getJson("/api/v1/children/{$this->child->id}/schedule")
            ->assertOk()
            ->assertJsonPath('data.0.attendance_status', null);

        Enrollment::query()->where('child_id', $this->child->id)->update(['status' => 'ended']);

        $this->getJson("/api/v1/children/{$this->child->id}/schedule")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_schedule_accepts_a_custom_range_and_validates_it(): void
    {
        $this->makeSession('2026-11-20 13:00:00');

        $this->getJson("/api/v1/children/{$this->child->id}/schedule?from=2026-11-20&to=2026-11-20")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/children/{$this->child->id}/schedule?from=20-11-2026&to=x")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'to']);

        $this->getJson("/api/v1/children/{$this->child->id}/schedule?from=2026-11-20&to=2026-11-19")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);

        $this->withHeader('Accept-Language', 'en')
            ->getJson("/api/v1/children/{$this->child->id}/schedule?from=2026-01-01&to=2026-12-31")
            ->assertUnprocessable()
            ->assertJsonPath('errors.to.0', 'The date range cannot be longer than 62 days.');
    }

    public function test_excused_is_not_an_absence_in_the_summary(): void
    {
        $this->mark($this->makeSession('2026-10-20 13:00:00'), 'present');
        $this->mark($this->makeSession('2026-10-22 13:00:00'), 'absent');
        $this->mark($this->makeSession('2026-10-24 13:00:00'), 'excused');

        $this->getJson("/api/v1/children/{$this->child->id}/attendance")
            ->assertOk()
            ->assertJsonPath('data.0.status', 'excused')
            ->assertJsonPath('summary.present', 1)
            ->assertJsonPath('summary.absent', 1)
            ->assertJsonPath('summary.excused', 1)
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.attendance_rate', 50);
    }

    public function test_attendance_lists_records_newest_first_with_a_summary(): void
    {
        $first = $this->makeSession('2026-10-20 13:00:00');
        $second = $this->makeSession('2026-10-22 13:00:00');
        $third = $this->makeSession('2026-10-24 13:00:00');
        $this->mark($first, 'present');
        $this->mark($second, 'absent');
        $this->mark($third, 'present');
        $this->mark($third, 'absent', Child::factory()->create());

        $response = $this->getJson("/api/v1/children/{$this->child->id}/attendance")->assertOk();

        $response->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.session_id', $third->id)
            ->assertJsonPath('data.2.session_id', $first->id)
            ->assertJsonPath('data.0.session.group.academy.name_en', $this->group->academy->name_en)
            ->assertJsonPath('summary.present', 2)
            ->assertJsonPath('summary.absent', 1)
            ->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.attendance_rate', 66.7);
    }

    public function test_attendance_summary_follows_the_date_range_and_handles_no_records(): void
    {
        $this->mark($this->makeSession('2026-10-20 13:00:00'), 'present');
        $this->mark($this->makeSession('2026-10-24 13:00:00'), 'absent');

        $this->getJson("/api/v1/children/{$this->child->id}/attendance?from=2026-10-23&to=2026-10-25")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.attendance_rate', 0);

        $this->getJson("/api/v1/children/{$this->child->id}/attendance?from=2027-01-01")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('summary.total', 0)
            ->assertJsonPath('summary.attendance_rate', null);
    }

    public function test_attendance_validates_its_filters(): void
    {
        $this->getJson("/api/v1/children/{$this->child->id}/attendance?from=bad&per_page=0")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'per_page']);

        $this->withHeader('Accept-Language', 'en')
            ->getJson("/api/v1/children/{$this->child->id}/attendance?per_page=500")
            ->assertUnprocessable()
            ->assertJsonPath('errors.per_page.0', 'The page size must be a whole number between 1 and 100.');
    }
}
