<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Child;
use App\Models\Enrollment;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChildrenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
    }

    public function test_user_lists_only_own_active_children(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $older = Child::factory()->for($user)->create(['birth_date' => '2015-01-01']);
        $younger = Child::factory()->for($user)->create(['birth_date' => '2019-06-01']);
        Child::factory()->for($user)->create(['is_active' => false]);
        Child::factory()->create();

        $this->getJson('/api/v1/children')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.1.id', $younger->id);
    }

    public function test_user_can_add_a_child(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $birthDate = now()->subYears(8)->toDateString();

        $this->postJson('/api/v1/children', [
            'full_name' => '  ليان  ',
            'birth_date' => $birthDate,
            'gender' => 'female',
            'health_notes' => 'ربو خفيف',
            'user_id' => User::factory()->create()->id,
            'is_active' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('message', __('children.created', [], 'ar'))
            ->assertJsonPath('data.full_name', 'ليان')
            ->assertJsonPath('data.birth_date', $birthDate)
            ->assertJsonPath('data.age', 8)
            ->assertJsonPath('data.gender', 'female')
            ->assertJsonPath('data.health_notes', 'ربو خفيف');

        $child = Child::query()->sole();
        $this->assertSame($user->id, $child->user_id);
        $this->assertTrue($child->is_active);
    }

    public function test_store_validates_input_in_both_languages(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/children', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.full_name.0', __('children.validation.full_name_required', [], 'ar'))
            ->assertJsonPath('errors.birth_date.0', __('children.validation.birth_date_required', [], 'ar'))
            ->assertJsonPath('errors.gender.0', __('children.validation.gender_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/children', [
                'full_name' => ['x'],
                'birth_date' => '01/02/2018',
                'gender' => 'other',
                'health_notes' => str_repeat('a', 1001),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.full_name.0', 'The child\'s full name must be text.')
            ->assertJsonPath('errors.birth_date.0', 'The birth date must be in YYYY-MM-DD format.')
            ->assertJsonPath('errors.gender.0', 'The gender must be male or female.')
            ->assertJsonPath('errors.health_notes.0', 'The health notes must be text of at most 1000 characters.');
    }

    public function test_birth_date_must_be_in_the_past_and_recent(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach ([now()->toDateString(), now()->addDay()->toDateString(), now()->subYears(31)->toDateString()] as $birthDate) {
            $this->postJson('/api/v1/children', ['full_name' => 'Ali', 'birth_date' => $birthDate, 'gender' => 'male'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.birth_date.0', __('children.validation.birth_date_range', [], 'ar'));
        }

        $this->assertSame(0, Child::query()->count());
    }

    public function test_user_can_view_own_child(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $child = Child::factory()->for($user)->create();

        $this->getJson("/api/v1/children/{$child->id}")
            ->assertOk()
            ->assertExactJsonStructure(['data' => ['id', 'full_name', 'birth_date', 'age', 'gender', 'health_notes', 'created_at', 'updated_at']])
            ->assertJsonPath('data.id', $child->id);
    }

    public function test_user_can_partially_update_own_child(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $child = Child::factory()->for($user)->create(['full_name' => 'Old', 'gender' => 'male', 'health_notes' => 'notes']);

        $this->patchJson("/api/v1/children/{$child->id}", ['full_name' => 'New Name', 'health_notes' => null])
            ->assertOk()
            ->assertJsonPath('message', __('children.updated', [], 'ar'))
            ->assertJsonPath('data.full_name', 'New Name')
            ->assertJsonPath('data.gender', 'male')
            ->assertJsonPath('data.health_notes', null);

        $this->patchJson("/api/v1/children/{$child->id}", ['gender' => 'x', 'full_name' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.gender.0', __('children.validation.gender_invalid', [], 'ar'))
            ->assertJsonPath('errors.full_name.0', __('children.validation.full_name_required', [], 'ar'));
    }

    public function test_other_users_children_look_like_they_do_not_exist(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $child = Child::factory()->create(['full_name' => 'Other']);

        $this->getJson("/api/v1/children/{$child->id}")
            ->assertNotFound()
            ->assertJsonPath('message', __('api.not_found', [], 'ar'));
        $this->patchJson("/api/v1/children/{$child->id}", ['full_name' => 'Hacked'])->assertNotFound();
        $this->deleteJson("/api/v1/children/{$child->id}")->assertNotFound();

        $this->assertSame('Other', $child->fresh()->full_name);
    }

    public function test_missing_and_archived_children_return_not_found(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $archived = Child::factory()->for($user)->create(['is_active' => false]);

        $this->getJson('/api/v1/children/999999')->assertNotFound();
        $this->getJson("/api/v1/children/{$archived->id}")->assertNotFound();
        $this->patchJson("/api/v1/children/{$archived->id}", ['full_name' => 'Back'])->assertNotFound();
    }

    public function test_child_without_history_is_deleted(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $child = Child::factory()->for($user)->create();

        $this->deleteJson("/api/v1/children/{$child->id}")
            ->assertOk()
            ->assertJsonPath('message', __('children.deleted', [], 'ar'));

        $this->assertModelMissing($child);
    }

    public function test_child_with_history_is_archived_instead_of_deleted(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $child = Child::factory()->for($user)->create();
        Enrollment::factory()->create(['child_id' => $child->id, 'status' => 'ended']);
        Attendance::factory()->create(['child_id' => $child->id]);

        $this->deleteJson("/api/v1/children/{$child->id}")
            ->assertOk()
            ->assertJsonPath('message', __('children.archived', [], 'ar'));

        $this->assertFalse($child->fresh()->is_active);
        $this->getJson('/api/v1/children')->assertJsonCount(0, 'data');
    }

    public function test_child_with_active_enrollment_cannot_be_removed(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $child = Child::factory()->for($user)->create();
        Enrollment::factory()->create(['child_id' => $child->id, 'status' => 'active']);

        $this->deleteJson("/api/v1/children/{$child->id}")
            ->assertConflict()
            ->assertJsonPath('message', __('children.has_active_enrollment', [], 'ar'));

        $this->assertTrue($child->fresh()->is_active);
    }

    public function test_children_routes_require_user_token(): void
    {
        $child = Child::factory()->create();

        $this->getJson('/api/v1/children')->assertUnauthorized();
        $this->postJson('/api/v1/children', [])->assertUnauthorized();

        Sanctum::actingAs(Staff::factory()->create());

        $this->getJson('/api/v1/children')->assertForbidden();
        $this->getJson("/api/v1/children/{$child->id}")->assertForbidden();
        $this->deleteJson("/api/v1/children/{$child->id}")->assertForbidden();
    }
}
