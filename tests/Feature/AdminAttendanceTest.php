<?php

namespace Tests\Feature;

use App\Models\Academy;
use App\Models\AcademyGroup;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\Enrollment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\TrainingSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Staff $staff;

    private AcademyGroup $group;

    private Child $enrolledChild;

    private Child $secondChild;

    private TrainingSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');

        // Monday 2026-10-26 11:00 in Damascus (UTC+3).
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));

        $this->staff = $this->staffWithPermissions(['attendance.manage']);
        Sanctum::actingAs($this->staff);

        $this->group = AcademyGroup::factory()->create(['academy_id' => Academy::factory()->create()->id]);
        $this->enrolledChild = Child::factory()->create();
        $this->secondChild = Child::factory()->create();
        Enrollment::factory()->create(['child_id' => $this->enrolledChild->id, 'group_id' => $this->group->id]);
        Enrollment::factory()->create(['child_id' => $this->secondChild->id, 'group_id' => $this->group->id]);
        $this->session = $this->makeSession('2026-10-26 05:00:00', '2026-10-26 06:30:00');
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
    private function makeSession(string $startsUtc, string $endsUtc, array $overrides = []): TrainingSession
    {
        return TrainingSession::factory()->create(array_merge([
            'group_id' => $this->group->id,
            'starts_at' => $startsUtc,
            'ends_at' => $endsUtc,
        ], $overrides));
    }

    private function attendanceUrl(?TrainingSession $session = null): string
    {
        return '/api/v1/admin/sessions/'.($session ?? $this->session)->id.'/attendance';
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $endpoints = [
            ['getJson', '/api/v1/admin/sessions?date=2026-10-26'],
            ['getJson', $this->attendanceUrl()],
            ['putJson', $this->attendanceUrl()],
        ];

        $this->app['auth']->forgetGuards();

        foreach ($endpoints as [$method, $url]) {
            $this->withHeader('Accept-Language', 'ar')->{$method}($url)->assertUnauthorized();
        }

        Sanctum::actingAs($this->staffWithPermissions(['sessions.manage']));

        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    public function test_sessions_of_a_local_day_are_listed_with_counters(): void
    {
        Attendance::factory()->present()->create(['session_id' => $this->session->id, 'child_id' => $this->enrolledChild->id, 'marked_by_staff_id' => $this->staff->id]);
        Attendance::factory()->absent()->create(['session_id' => $this->session->id, 'child_id' => $this->secondChild->id, 'marked_by_staff_id' => $this->staff->id]);
        $this->makeSession('2026-10-26 10:00:00', '2026-10-26 11:00:00', ['status' => 'cancelled']);
        $this->makeSession('2026-10-27 10:00:00', '2026-10-27 11:00:00');

        $response = $this->getJson('/api/v1/admin/sessions?date=2026-10-26')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->session->id)
            ->assertJsonPath('data.0.enrolled_count', 2)
            ->assertJsonPath('data.0.present_count', 1)
            ->assertJsonPath('data.0.absent_count', 1);
    }

    public function test_listing_validates_the_date(): void
    {
        $this->getJson('/api/v1/admin/sessions')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);

        $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/admin/sessions?date=26-10-2026&group_id=abc')
            ->assertUnprocessable()
            ->assertJsonPath('errors.date.0', 'The date must use the YYYY-MM-DD format.')
            ->assertJsonValidationErrors(['group_id']);
    }

    public function test_roster_shows_enrolled_children_with_their_status(): void
    {
        Attendance::factory()->present()->create(['session_id' => $this->session->id, 'child_id' => $this->enrolledChild->id, 'marked_by_staff_id' => $this->staff->id]);
        Enrollment::factory()->ended()->create(['child_id' => Child::factory()->create()->id, 'group_id' => $this->group->id]);

        $response = $this->getJson($this->attendanceUrl())->assertOk();

        $response->assertJsonCount(2, 'data');
        $statuses = collect($response->json('data'))->pluck('status', 'child_id');
        $this->assertSame('present', $statuses[$this->enrolledChild->id]);
        $this->assertNull($statuses[$this->secondChild->id]);
    }

    public function test_attendance_is_marked_and_can_be_corrected(): void
    {
        $this->putJson($this->attendanceUrl(), ['records' => [
            ['child_id' => $this->enrolledChild->id, 'status' => 'present'],
            ['child_id' => $this->secondChild->id, 'status' => 'absent'],
        ]])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'present');

        $this->putJson($this->attendanceUrl(), ['records' => [
            ['child_id' => $this->secondChild->id, 'status' => 'present'],
        ]])->assertOk();

        $this->assertSame(2, Attendance::query()->count());
        $this->assertDatabaseHas('attendance', [
            'session_id' => $this->session->id,
            'child_id' => $this->secondChild->id,
            'status' => 'present',
            'marked_by_staff_id' => $this->staff->id,
        ]);
        $this->assertSame(2, AuditLog::query()->where('action', 'attendance.marked')->count());
    }

    public function test_excused_status_is_accepted_and_counted_separately(): void
    {
        $this->putJson($this->attendanceUrl(), ['records' => [
            ['child_id' => $this->enrolledChild->id, 'status' => 'excused'],
        ]])->assertOk()->assertJsonPath('data.0.status', 'excused');

        $this->getJson('/api/v1/admin/sessions?date=2026-10-26')
            ->assertOk()
            ->assertJsonPath('data.0.excused_count', 1)
            ->assertJsonPath('data.0.absent_count', 0);
    }

    public function test_invalid_records_are_rejected_in_both_languages(): void
    {
        $this->putJson($this->attendanceUrl(), [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.records.0', 'سجلات الحضور مطلوبة.');

        $response = $this->withHeader('Accept-Language', 'en')
            ->putJson($this->attendanceUrl(), ['records' => [['child_id' => 'x', 'status' => 'late'], ['status' => 'present']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['records.0.child_id', 'records.0.status', 'records.1.child_id']);

        $this->assertSame('The child must be a whole number.', $response->json('errors')['records.0.child_id'][0]);

        $this->putJson($this->attendanceUrl(), ['records' => [
            ['child_id' => $this->enrolledChild->id, 'status' => 'present'],
            ['child_id' => $this->enrolledChild->id, 'status' => 'absent'],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['records.0.child_id']);

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_a_child_outside_the_group_is_rejected_and_nothing_is_saved(): void
    {
        $outsider = Child::factory()->create();
        $ended = Child::factory()->create();
        Enrollment::factory()->ended()->create(['child_id' => $ended->id, 'group_id' => $this->group->id]);

        foreach ([$outsider, $ended] as $child) {
            $this->putJson($this->attendanceUrl(), ['records' => [
                ['child_id' => $this->enrolledChild->id, 'status' => 'present'],
                ['child_id' => $child->id, 'status' => 'present'],
            ]])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['records.1.child_id']);
        }

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_future_and_cancelled_sessions_cannot_be_marked(): void
    {
        $payload = ['records' => [['child_id' => $this->enrolledChild->id, 'status' => 'present']]];

        $future = $this->makeSession('2026-10-26 12:00:00', '2026-10-26 13:00:00');
        $this->putJson($this->attendanceUrl($future), $payload)->assertConflict();

        $cancelled = $this->makeSession('2026-10-26 05:00:00', '2026-10-26 06:00:00', ['status' => 'cancelled']);
        $this->putJson($this->attendanceUrl($cancelled), $payload)->assertConflict();

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_missing_session_returns_not_found(): void
    {
        $this->getJson('/api/v1/admin/sessions/999999/attendance')->assertNotFound();
        $this->putJson('/api/v1/admin/sessions/999999/attendance', ['records' => []])->assertNotFound();
    }
}
