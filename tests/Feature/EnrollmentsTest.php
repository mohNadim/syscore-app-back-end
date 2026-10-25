<?php

namespace Tests\Feature;

use App\Models\Academy;
use App\Models\AcademyGroup;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\GroupSchedule;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserCategory;
use App\Models\WaitlistEntry;
use App\Services\Academies\EnrollmentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnrollmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AcademyGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));
        $this->user = Sanctum::actingAs(User::factory()->create());
        $this->group = AcademyGroup::factory()->create([
            'academy_id' => Academy::factory()->create()->id,
            'age_min' => 6,
            'age_max' => 12,
            'gender' => 'mixed',
            'capacity' => 2,
            'monthly_price' => 100000,
        ]);
    }

    private function child(string $birthDate = '2018-01-01', string $gender = 'male', ?User $owner = null): Child
    {
        return Child::factory()->for($owner ?? $this->user)->create(['birth_date' => $birthDate, 'gender' => $gender]);
    }

    /**
     * @return array<string, int>
     */
    private function payload(Child $child, ?AcademyGroup $group = null): array
    {
        return ['child_id' => $child->id, 'group_id' => ($group ?? $this->group)->id];
    }

    public function test_enrolling_creates_an_active_enrollment_with_an_unpaid_subscription(): void
    {
        $child = $this->child();

        $this->postJson('/api/v1/enrollments', $this->payload($child))
            ->assertCreated()
            ->assertJsonPath('message', __('enrollments.enrolled', [], 'ar'))
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.child_id', $child->id)
            ->assertJsonPath('data.group.id', $this->group->id)
            ->assertJsonPath('data.latest_subscription.status', 'pending_payment')
            ->assertJsonPath('data.latest_subscription.base_price', 100000)
            ->assertJsonPath('data.latest_subscription.final_price', 100000)
            ->assertJsonPath('data.latest_subscription.period_start', null);

        $enrollment = Enrollment::query()->sole();
        $this->assertNull($enrollment->created_by_staff_id);
        $this->assertTrue(AuditLog::query()->where('action', 'enrollment.created')->where('entity_id', $enrollment->id)->where('actor_user_id', $this->user->id)->exists());
    }

    public function test_subscription_price_includes_the_users_discount(): void
    {
        $category = UserCategory::factory()->create();
        $this->user->categories()->attach($category);
        $discount = Discount::factory()->create(['type' => 'percent', 'value' => 25, 'target' => 'subscription']);
        $discount->categories()->attach($category);

        $this->postJson('/api/v1/enrollments', $this->payload($this->child()))
            ->assertCreated()
            ->assertJsonPath('data.latest_subscription.base_price', 100000)
            ->assertJsonPath('data.latest_subscription.discount_amount', 25000)
            ->assertJsonPath('data.latest_subscription.final_price', 75000)
            ->assertJsonPath('data.latest_subscription.discount.id', $discount->id);
    }

    public function test_age_boundaries_use_full_years_at_the_local_today(): void
    {
        // Local today is 2026-10-26: born 2020-10-26 is exactly 6, born 2020-10-27 is still 5.
        $this->postJson('/api/v1/enrollments', $this->payload($this->child('2020-10-27')))
            ->assertUnprocessable()
            ->assertJsonPath('errors.group_id.0', __('enrollments.not_eligible', ['reason' => __('enrollments.reasons.age', [], 'ar')], 'ar'));

        $this->postJson('/api/v1/enrollments', $this->payload($this->child('2020-10-26')))->assertCreated();

        // Born 2013-10-26 is exactly 13 (too old); 2013-10-27 is still 12.
        $this->postJson('/api/v1/enrollments', $this->payload($this->child('2013-10-26')))->assertUnprocessable();
        $this->group->update(['capacity' => 5]);
        $this->postJson('/api/v1/enrollments', $this->payload($this->child('2013-10-27')))->assertCreated();
    }

    public function test_gender_must_match_unless_the_group_is_mixed(): void
    {
        $this->group->update(['gender' => 'female']);

        $this->postJson('/api/v1/enrollments', $this->payload($this->child(gender: 'male')))
            ->assertUnprocessable()
            ->assertJsonPath('errors.group_id.0', __('enrollments.not_eligible', ['reason' => __('enrollments.reasons.gender', [], 'ar')], 'ar'));

        $this->postJson('/api/v1/enrollments', $this->payload($this->child(gender: 'female')))->assertCreated();
    }

    public function test_inactive_group_or_academy_is_not_eligible(): void
    {
        $child = $this->child();
        $this->group->update(['is_active' => false]);
        $this->postJson('/api/v1/enrollments', $this->payload($child))->assertUnprocessable();

        $this->group->update(['is_active' => true]);
        $this->group->academy->update(['is_active' => false]);
        $this->postJson('/api/v1/enrollments', $this->payload($child))->assertUnprocessable();

        $this->assertSame(0, Enrollment::query()->count());
    }

    public function test_full_group_puts_the_child_on_the_waitlist_with_a_position(): void
    {
        $this->postJson('/api/v1/enrollments', $this->payload($this->child()))->assertCreated();
        $this->postJson('/api/v1/enrollments', $this->payload($this->child()))->assertCreated();

        $first = $this->child();
        $this->postJson('/api/v1/enrollments', $this->payload($first))
            ->assertStatus(202)
            ->assertJsonPath('message', __('enrollments.waitlisted', [], 'ar'))
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.child_id', $first->id);

        $this->travel(1)->second();
        $second = $this->child();
        $this->postJson('/api/v1/enrollments', $this->payload($second))
            ->assertStatus(202)
            ->assertJsonPath('data.position', 2);

        $this->assertSame(2, Enrollment::query()->count());
        $this->assertSame(2, WaitlistEntry::query()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'waitlist.joined')->exists());

        $this->getJson('/api/v1/waitlist')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.position', 1)
            ->assertJsonPath('data.1.position', 2);
    }

    public function test_pending_payment_subscription_holds_its_seat(): void
    {
        $this->group->update(['capacity' => 1]);
        $this->postJson('/api/v1/enrollments', $this->payload($this->child()))->assertCreated();

        $this->postJson('/api/v1/enrollments', $this->payload($this->child()))->assertStatus(202);
        $this->assertSame(0, app(EnrollmentService::class)->seatsLeft($this->group->fresh()));
    }

    public function test_offered_entry_can_enroll_even_when_the_group_is_full(): void
    {
        $this->group->update(['capacity' => 1]);
        $child = $this->child();
        Enrollment::factory()->create(['group_id' => $this->group->id]);
        $entry = WaitlistEntry::factory()->create(['group_id' => $this->group->id, 'child_id' => $child->id, 'status' => 'offered', 'offered_at' => now()]);

        $this->postJson('/api/v1/enrollments', $this->payload($child))->assertCreated();

        $this->assertSame('enrolled', $entry->fresh()->status);
        $this->assertSame(2, Enrollment::query()->where('status', 'active')->count());
    }

    public function test_offered_seat_is_not_available_to_other_children(): void
    {
        $this->group->update(['capacity' => 1]);
        WaitlistEntry::factory()->create(['group_id' => $this->group->id, 'status' => 'offered', 'offered_at' => now()]);

        $this->postJson('/api/v1/enrollments', $this->payload($this->child()))->assertStatus(202);
    }

    public function test_already_enrolled_and_already_waitlisted_are_conflicts(): void
    {
        $this->group->update(['capacity' => 1]);
        $enrolled = $this->child();
        $waiting = $this->child();

        $this->postJson('/api/v1/enrollments', $this->payload($enrolled))->assertCreated();
        $this->postJson('/api/v1/enrollments', $this->payload($enrolled))
            ->assertConflict()
            ->assertJsonPath('message', __('enrollments.already_enrolled', [], 'ar'));

        $this->postJson('/api/v1/enrollments', $this->payload($waiting))->assertStatus(202);
        $this->postJson('/api/v1/enrollments', $this->payload($waiting))
            ->assertConflict()
            ->assertJsonPath('message', __('enrollments.already_waitlisted', [], 'ar'));

        $this->assertSame(1, WaitlistEntry::query()->count());
    }

    public function test_rejoining_after_leaving_resets_the_existing_entry(): void
    {
        $this->group->update(['capacity' => 1]);
        Enrollment::factory()->create(['group_id' => $this->group->id]);
        $child = $this->child();
        $entry = WaitlistEntry::factory()->create(['group_id' => $this->group->id, 'child_id' => $child->id, 'status' => 'removed', 'created_at' => now()->subDays(3)]);

        $this->postJson('/api/v1/enrollments', $this->payload($child))
            ->assertStatus(202)
            ->assertJsonPath('data.id', $entry->id)
            ->assertJsonPath('data.status', 'waiting');

        $this->assertSame(1, WaitlistEntry::query()->count());
        $this->assertTrue($entry->fresh()->created_at->equalTo(now()));
    }

    public function test_a_child_can_enroll_again_after_the_enrollment_ended(): void
    {
        $child = $this->child();
        Enrollment::factory()->ended()->create(['child_id' => $child->id, 'group_id' => $this->group->id]);

        $this->postJson('/api/v1/enrollments', $this->payload($child))->assertCreated();
    }

    public function test_other_parents_child_looks_like_it_does_not_exist(): void
    {
        $stranger = $this->child(owner: User::factory()->create());

        $this->postJson('/api/v1/enrollments', $this->payload($stranger))->assertNotFound();
        $this->getJson("/api/v1/children/{$stranger->id}/eligible-groups")->assertNotFound();
        $this->assertSame(0, Enrollment::query()->count());
    }

    public function test_store_validates_input_in_both_languages(): void
    {
        $this->postJson('/api/v1/enrollments', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.child_id.0', __('enrollments.validation.child_id_invalid', [], 'ar'))
            ->assertJsonPath('errors.group_id.0', __('enrollments.validation.group_id_invalid', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/enrollments', ['child_id' => 'x', 'group_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonPath('errors.child_id.0', __('enrollments.validation.child_id_invalid', [], 'en'))
            ->assertJsonPath('errors.group_id.0', __('enrollments.validation.group_id_invalid', [], 'en'));
    }

    public function test_eligible_groups_lists_only_matching_groups_with_seats_price_and_status(): void
    {
        $child = $this->child(gender: 'female');
        $academy = $this->group->academy;
        $girls = AcademyGroup::factory()->create(['academy_id' => $academy->id, 'age_min' => 6, 'age_max' => 12, 'gender' => 'female', 'capacity' => 1, 'monthly_price' => 50000]);
        AcademyGroup::factory()->create(['academy_id' => $academy->id, 'age_min' => 6, 'age_max' => 12, 'gender' => 'male']);
        AcademyGroup::factory()->create(['academy_id' => $academy->id, 'age_min' => 13, 'age_max' => 15, 'gender' => 'mixed']);
        AcademyGroup::factory()->inactive()->create(['academy_id' => $academy->id, 'age_min' => 6, 'age_max' => 12, 'gender' => 'mixed']);
        GroupSchedule::factory()->create(['group_id' => $girls->id, 'valid_to' => null]);
        GroupSchedule::factory()->create(['group_id' => $girls->id, 'valid_from' => '2025-01-01', 'valid_to' => '2025-12-31']);
        Enrollment::factory()->create(['group_id' => $girls->id]);

        $this->postJson('/api/v1/enrollments', $this->payload($child))->assertCreated();

        $response = $this->getJson("/api/v1/children/{$child->id}/eligible-groups")->assertOk()->assertJsonCount(2, 'data');
        $data = collect($response->json('data'))->keyBy('id');

        $this->assertSame(0, $data[$girls->id]['seats_left']);
        $this->assertTrue($data[$girls->id]['is_full']);
        $this->assertSame(50000, $data[$girls->id]['price']['final_price']);
        $this->assertNull($data[$girls->id]['child_status']);
        $this->assertCount(1, $data[$girls->id]['schedules']);
        $this->assertNotNull($data[$girls->id]['schedules'][0]['unit']);
        $this->assertSame(1, $data[$this->group->id]['seats_left']);
        $this->assertFalse($data[$this->group->id]['is_full']);
        $this->assertSame('enrolled', $data[$this->group->id]['child_status']);
        $this->assertNotNull($data[$this->group->id]['academy']['sport']);
    }

    public function test_eligible_groups_reports_waitlisted_and_offered_status(): void
    {
        $child = $this->child();
        $other = AcademyGroup::factory()->create(['academy_id' => $this->group->academy_id, 'age_min' => 6, 'age_max' => 12, 'gender' => 'mixed']);
        WaitlistEntry::factory()->create(['group_id' => $this->group->id, 'child_id' => $child->id, 'status' => 'waiting']);
        WaitlistEntry::factory()->create(['group_id' => $other->id, 'child_id' => $child->id, 'status' => 'offered']);

        $data = collect($this->getJson("/api/v1/children/{$child->id}/eligible-groups")->assertOk()->json('data'))->keyBy('id');

        $this->assertSame('waitlisted', $data[$this->group->id]['child_status']);
        $this->assertSame('offered', $data[$other->id]['child_status']);
        $this->assertSame($this->group->capacity - 0, $data[$this->group->id]['seats_left']);
        $this->assertSame($other->capacity - 1, $data[$other->id]['seats_left']);
    }

    public function test_index_lists_only_own_childrens_enrollments_with_filters(): void
    {
        $mine = $this->child();
        $other = $this->child();
        $own = Enrollment::factory()->create(['child_id' => $mine->id, 'group_id' => $this->group->id]);
        Enrollment::factory()->ended()->create(['child_id' => $other->id]);
        Enrollment::factory()->create();

        $this->getJson('/api/v1/enrollments')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/enrollments?status=active')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson("/api/v1/enrollments?child_id={$other->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/enrollments?status=bogus')->assertUnprocessable()
            ->assertJsonPath('errors.status.0', __('enrollments.validation.status_invalid', [], 'ar'));
    }

    public function test_leaving_the_waitlist_marks_the_entry_removed(): void
    {
        $entry = WaitlistEntry::factory()->create(['child_id' => $this->child()->id, 'status' => 'offered']);

        $this->deleteJson("/api/v1/waitlist/{$entry->id}")
            ->assertOk()
            ->assertJsonPath('message', __('enrollments.waitlist_left', [], 'ar'));

        $this->assertSame('removed', $entry->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'waitlist.left')->where('entity_id', $entry->id)->exists());

        $this->deleteJson("/api/v1/waitlist/{$entry->id}")->assertUnprocessable();
    }

    public function test_other_parents_waitlist_entry_is_not_found(): void
    {
        $entry = WaitlistEntry::factory()->create(['child_id' => $this->child(owner: User::factory()->create())->id]);

        $this->deleteJson("/api/v1/waitlist/{$entry->id}")->assertNotFound();
        $this->assertSame('waiting', $entry->fresh()->status);
        $this->getJson('/api/v1/waitlist')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_enrolling_locks_the_group_row_before_counting_seats(): void
    {
        $this->group->update(['capacity' => 1]);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->postJson('/api/v1/enrollments', $this->payload($this->child()))->assertCreated();

        $lock = collect($queries)->search(fn (string $sql) => str_contains($sql, 'from `academy_groups`') && str_contains($sql, 'for update'));
        $count = collect($queries)->search(fn (string $sql) => str_contains($sql, 'from `enrollments`') && str_contains($sql, 'count(*)'));
        $this->assertNotFalse($lock);
        $this->assertLessThan($count, $lock);
    }

    public function test_access_control(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->postJson('/api/v1/enrollments', [])->assertUnauthorized();
        $this->getJson('/api/v1/waitlist')->assertUnauthorized();

        Sanctum::actingAs(Staff::factory()->create());
        $this->postJson('/api/v1/enrollments', [])->assertForbidden();
        $this->getJson('/api/v1/enrollments')->assertForbidden();
        $this->getJson('/api/v1/waitlist')->assertForbidden();
    }
}
