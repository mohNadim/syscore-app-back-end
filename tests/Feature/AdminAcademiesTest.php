<?php

namespace Tests\Feature;

use App\Models\Academy;
use App\Models\AcademyGroup;
use App\Models\AuditLog;
use App\Models\Coach;
use App\Models\Enrollment;
use App\Models\GroupSchedule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sport;
use App\Models\Staff;
use App\Models\User;
use App\Services\PublicImageStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAcademiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withHeader('Accept-Language', 'ar');
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

    private function pathOf(string $url): string
    {
        return ltrim(substr($url, strlen(rtrim(Storage::disk('public')->url(''), '/'))), '/');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function groupPayload(array $overrides = []): array
    {
        return array_merge([
            'name_ar' => 'مجموعة ا',
            'age_min' => 6,
            'age_max' => 10,
            'gender' => 'mixed',
            'capacity' => 15,
            'monthly_price' => 100000,
        ], $overrides);
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $academy = Academy::factory()->create();
        $group = AcademyGroup::factory()->create(['academy_id' => $academy->id]);

        $endpoints = [
            ['getJson', '/api/v1/admin/academies'],
            ['postJson', '/api/v1/admin/academies'],
            ['getJson', "/api/v1/admin/academies/{$academy->id}"],
            ['putJson', "/api/v1/admin/academies/{$academy->id}"],
            ['deleteJson', "/api/v1/admin/academies/{$academy->id}"],
            ['getJson', "/api/v1/admin/academies/{$academy->id}/groups"],
            ['postJson', "/api/v1/admin/academies/{$academy->id}/groups"],
            ['getJson', "/api/v1/admin/groups/{$group->id}"],
            ['putJson', "/api/v1/admin/groups/{$group->id}"],
            ['deleteJson', "/api/v1/admin/groups/{$group->id}"],
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

    public function test_academy_crud_and_audit(): void
    {
        $this->actingAsAcademiesManager();
        $sport = Sport::factory()->create();

        $response = $this->postJson('/api/v1/admin/academies', [
            'sport_id' => $sport->id,
            'name_ar' => 'أكاديمية الكرة',
            'name_en' => 'Football Academy',
            'description_ar' => 'وصف',
        ])
            ->assertCreated()
            ->assertJsonPath('message', __('admin_academies.academy_created', [], 'ar'))
            ->assertJsonPath('data.name_ar', 'أكاديمية الكرة')
            ->assertJsonPath('data.sport.id', $sport->id)
            ->assertJsonPath('data.cover_url', null)
            ->assertJsonPath('data.is_active', true);

        $id = $response->json('data.id');
        $this->assertTrue(AuditLog::query()->where('action', 'academy.created')->where('entity_id', $id)->exists());

        $this->getJson("/api/v1/admin/academies/{$id}")->assertOk()->assertJsonPath('data.id', $id);

        $this->putJson("/api/v1/admin/academies/{$id}", ['name_en' => 'FA', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('message', __('admin_academies.academy_updated', [], 'ar'))
            ->assertJsonPath('data.name_en', 'FA')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.name_ar', 'أكاديمية الكرة');
        $this->assertTrue(AuditLog::query()->where('action', 'academy.updated')->where('entity_id', $id)->exists());

        $this->deleteJson("/api/v1/admin/academies/{$id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_academies.academy_deleted', [], 'ar'));
        $this->assertDatabaseMissing('academies', ['id' => $id]);
        $this->assertTrue(AuditLog::query()->where('action', 'academy.deleted')->where('entity_id', $id)->exists());
    }

    public function test_academy_index_filters_and_counts_groups(): void
    {
        $this->actingAsAcademiesManager();
        $sportA = Sport::factory()->create();
        $sportB = Sport::factory()->create();
        $first = Academy::factory()->create(['sport_id' => $sportA->id]);
        $second = Academy::factory()->inactive()->create(['sport_id' => $sportB->id]);
        AcademyGroup::factory()->count(2)->create(['academy_id' => $first->id]);

        $this->getJson('/api/v1/admin/academies')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.0.groups_count', 2)
            ->assertJsonPath('data.1.groups_count', 0);

        $this->getJson("/api/v1/admin/academies?sport_id={$sportB->id}")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id);
        $this->getJson('/api/v1/admin/academies?is_active=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id);
        $this->getJson('/api/v1/admin/academies?sport_id=x&is_active=maybe')
            ->assertUnprocessable()
            ->assertJsonPath('errors.sport_id.0', __('admin_academies.validation.sport_id_invalid', [], 'ar'))
            ->assertJsonPath('errors.is_active.0', __('admin_academies.validation.is_active_invalid', [], 'ar'));
    }

    public function test_cover_upload_replace_and_remove(): void
    {
        $this->actingAsAcademiesManager();
        $sport = Sport::factory()->create();

        $response = $this->post('/api/v1/admin/academies', [
            'sport_id' => $sport->id,
            'name_ar' => 'س',
            'cover' => UploadedFile::fake()->image('a.png', 100, 100),
        ], ['Accept' => 'application/json'])->assertCreated();

        $id = $response->json('data.id');
        $firstPath = $this->pathOf($response->json('data.cover_url'));
        Storage::disk('public')->assertExists($firstPath);

        $second = $this->post("/api/v1/admin/academies/{$id}", [
            '_method' => 'PUT',
            'cover' => UploadedFile::fake()->image('b.webp', 100, 100),
        ], ['Accept' => 'application/json'])->assertOk();

        $secondPath = $this->pathOf($second->json('data.cover_url'));
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);

        $this->post("/api/v1/admin/academies/{$id}", ['_method' => 'PUT', 'remove_cover' => '1'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.cover_url', null);
        Storage::disk('public')->assertMissing($secondPath);

        $this->post('/api/v1/admin/academies', [
            'sport_id' => $sport->id,
            'name_ar' => 'س',
            'cover' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.cover.0', __('admin_academies.validation.cover_invalid', [], 'ar'));
    }

    public function test_academy_validation_errors_in_both_languages(): void
    {
        $this->actingAsAcademiesManager();

        $this->postJson('/api/v1/admin/academies', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.sport_id.0', __('admin_academies.validation.sport_id_required', [], 'ar'))
            ->assertJsonPath('errors.name_ar.0', __('admin_academies.validation.name_ar_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/academies', [
                'sport_id' => 9999,
                'name_ar' => str_repeat('a', 151),
                'name_en' => str_repeat('a', 151),
                'description_ar' => str_repeat('a', 5001),
                'is_active' => 'maybe',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.sport_id.0', 'The sport must be an existing sport.')
            ->assertJsonPath('errors.name_ar.0', 'The Arabic name must be text of at most 150 characters.')
            ->assertJsonPath('errors.name_en.0', 'The English name must be text of at most 150 characters.')
            ->assertJsonPath('errors.description_ar.0', 'The description must be text of at most 5000 characters.')
            ->assertJsonPath('errors.is_active.0', 'The active flag must be true or false.');

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/academies', [])
            ->assertJsonPath('errors.sport_id.0', 'The sport is required.');
    }

    public function test_deleting_academy_with_groups_conflicts_and_keeps_cover(): void
    {
        $this->actingAsAcademiesManager();
        $url = app(PublicImageStore::class)->store(UploadedFile::fake()->image('c.png'), 'academies');
        $academy = Academy::factory()->create(['cover_url' => $url]);
        AcademyGroup::factory()->create(['academy_id' => $academy->id]);

        $this->deleteJson("/api/v1/admin/academies/{$academy->id}")
            ->assertConflict()
            ->assertJsonPath('message', __('admin_academies.academy_in_use', [], 'ar'));

        $this->assertDatabaseHas('academies', ['id' => $academy->id]);
        Storage::disk('public')->assertExists($this->pathOf($url));
    }

    public function test_deleting_academy_removes_cover_file(): void
    {
        $this->actingAsAcademiesManager();
        $url = app(PublicImageStore::class)->store(UploadedFile::fake()->image('c.png'), 'academies');
        $academy = Academy::factory()->create(['cover_url' => $url]);

        $this->deleteJson("/api/v1/admin/academies/{$academy->id}")->assertOk();

        Storage::disk('public')->assertMissing($this->pathOf($url));
    }

    public function test_group_crud_and_audit(): void
    {
        $this->actingAsAcademiesManager();
        $academy = Academy::factory()->create();
        $coach = Coach::factory()->create();

        $response = $this->postJson("/api/v1/admin/academies/{$academy->id}/groups", $this->groupPayload(['coach_id' => $coach->id, 'level' => 'beginner']))
            ->assertCreated()
            ->assertJsonPath('message', __('admin_academies.group_created', [], 'ar'))
            ->assertJsonPath('data.academy_id', $academy->id)
            ->assertJsonPath('data.coach.id', $coach->id)
            ->assertJsonPath('data.subscription_days', 30)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.monthly_price', 100000);

        $id = $response->json('data.id');
        $this->assertTrue(AuditLog::query()->where('action', 'academy_group.created')->where('entity_id', $id)->exists());

        $this->getJson("/api/v1/admin/academies/{$academy->id}/groups")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        $this->putJson("/api/v1/admin/groups/{$id}", ['capacity' => 20, 'coach_id' => null, 'subscription_days' => 60])
            ->assertOk()
            ->assertJsonPath('message', __('admin_academies.group_updated', [], 'ar'))
            ->assertJsonPath('data.capacity', 20)
            ->assertJsonPath('data.coach_id', null)
            ->assertJsonPath('data.subscription_days', 60)
            ->assertJsonPath('data.age_min', 6);
        $this->assertTrue(AuditLog::query()->where('action', 'academy_group.updated')->where('entity_id', $id)->exists());

        $this->deleteJson("/api/v1/admin/groups/{$id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_academies.group_deleted', [], 'ar'));
        $this->assertDatabaseMissing('academy_groups', ['id' => $id]);
        $this->assertTrue(AuditLog::query()->where('action', 'academy_group.deleted')->where('entity_id', $id)->exists());
    }

    public function test_group_show_loads_academy_coach_and_schedules_with_unit(): void
    {
        $this->actingAsAcademiesManager();
        $coach = Coach::factory()->create();
        $group = AcademyGroup::factory()->create(['coach_id' => $coach->id]);
        GroupSchedule::factory()->create(['group_id' => $group->id]);

        $this->getJson("/api/v1/admin/groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('data.academy.id', $group->academy_id)
            ->assertJsonPath('data.coach.id', $coach->id)
            ->assertJsonCount(1, 'data.schedules')
            ->assertJsonStructure(['data' => ['schedules' => [['id', 'weekday', 'start_time', 'end_time', 'unit' => ['id']]]]]);
    }

    public function test_group_age_range_is_checked_on_store_and_against_stored_value_on_update(): void
    {
        $this->actingAsAcademiesManager();
        $academy = Academy::factory()->create();

        $this->postJson("/api/v1/admin/academies/{$academy->id}/groups", $this->groupPayload(['age_min' => 10, 'age_max' => 8]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.age_max.0', __('admin_academies.validation.age_max_below_min', [], 'ar'));

        $group = AcademyGroup::factory()->create(['academy_id' => $academy->id, 'age_min' => 6, 'age_max' => 12]);

        $this->putJson("/api/v1/admin/groups/{$group->id}", ['age_min' => 13])
            ->assertUnprocessable()
            ->assertJsonPath('errors.age_max.0', __('admin_academies.validation.age_max_below_min', [], 'ar'));

        $this->putJson("/api/v1/admin/groups/{$group->id}", ['age_max' => 5])
            ->assertUnprocessable()
            ->assertJsonPath('errors.age_max.0', __('admin_academies.validation.age_max_below_min', [], 'ar'));

        $this->putJson("/api/v1/admin/groups/{$group->id}", ['age_min' => 8, 'age_max' => 9])->assertOk();
    }

    public function test_group_capacity_cannot_drop_below_active_enrollments(): void
    {
        $this->actingAsAcademiesManager();
        $group = AcademyGroup::factory()->create(['capacity' => 10]);
        Enrollment::factory()->count(3)->create(['group_id' => $group->id]);
        Enrollment::factory()->ended()->create(['group_id' => $group->id]);

        $this->putJson("/api/v1/admin/groups/{$group->id}", ['capacity' => 2])
            ->assertUnprocessable()
            ->assertJsonPath('errors.capacity.0', __('admin_academies.validation.capacity_below_enrolled', ['count' => 3], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->putJson("/api/v1/admin/groups/{$group->id}", ['capacity' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('errors.capacity.0', 'The capacity cannot be lower than the 3 children currently enrolled.');

        $this->putJson("/api/v1/admin/groups/{$group->id}", ['capacity' => 3])->assertOk()->assertJsonPath('data.capacity', 3);
    }

    public function test_group_validation_errors_in_both_languages(): void
    {
        $this->actingAsAcademiesManager();
        $academy = Academy::factory()->create();
        $url = "/api/v1/admin/academies/{$academy->id}/groups";

        $this->postJson($url, [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name_ar.0', __('admin_academies.validation.name_ar_required', [], 'ar'))
            ->assertJsonPath('errors.age_min.0', __('admin_academies.validation.age_min_required', [], 'ar'))
            ->assertJsonPath('errors.age_max.0', __('admin_academies.validation.age_max_required', [], 'ar'))
            ->assertJsonPath('errors.gender.0', __('admin_academies.validation.gender_required', [], 'ar'))
            ->assertJsonPath('errors.capacity.0', __('admin_academies.validation.capacity_required', [], 'ar'))
            ->assertJsonPath('errors.monthly_price.0', __('admin_academies.validation.monthly_price_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson($url, [
                'coach_id' => 9999,
                'name_ar' => 'x',
                'age_min' => 31,
                'age_max' => 'abc',
                'gender' => 'other',
                'capacity' => 0,
                'level' => str_repeat('a', 51),
                'monthly_price' => -1,
                'subscription_days' => 366,
                'is_active' => 'maybe',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.coach_id.0', 'The coach must be an existing coach.')
            ->assertJsonPath('errors.age_min.0', 'The minimum age must be a whole number between 0 and 30.')
            ->assertJsonPath('errors.age_max.0', 'The maximum age must be a whole number between 0 and 30.')
            ->assertJsonPath('errors.gender.0', 'The gender must be male, female or mixed.')
            ->assertJsonPath('errors.capacity.0', 'The capacity must be a whole number between 1 and 500.')
            ->assertJsonPath('errors.level.0', 'The level must be text of at most 50 characters.')
            ->assertJsonPath('errors.monthly_price.0', 'The monthly price must be a whole number between 0 and 100000000.')
            ->assertJsonPath('errors.subscription_days.0', 'The subscription length must be a whole number of days between 1 and 365.')
            ->assertJsonPath('errors.is_active.0', 'The active flag must be true or false.');
    }

    public function test_deleting_group_with_enrollments_conflicts(): void
    {
        $this->actingAsAcademiesManager();
        $group = AcademyGroup::factory()->create();
        Enrollment::factory()->create(['group_id' => $group->id]);

        $this->deleteJson("/api/v1/admin/groups/{$group->id}")
            ->assertConflict()
            ->assertJsonPath('message', __('admin_academies.group_in_use', [], 'ar'));
        $this->assertDatabaseHas('academy_groups', ['id' => $group->id]);
    }

    public function test_missing_records_return_not_found(): void
    {
        $this->actingAsAcademiesManager();

        $this->getJson('/api/v1/admin/academies/9999')->assertNotFound();
        $this->getJson('/api/v1/admin/academies/9999/groups')->assertNotFound();
        $this->postJson('/api/v1/admin/academies/9999/groups', $this->groupPayload())->assertNotFound();
        $this->getJson('/api/v1/admin/groups/9999')->assertNotFound();
        $this->putJson('/api/v1/admin/groups/9999', [])->assertNotFound();
        $this->deleteJson('/api/v1/admin/groups/9999')->assertNotFound();
    }
}
