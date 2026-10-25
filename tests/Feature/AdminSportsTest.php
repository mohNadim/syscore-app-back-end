<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BookableUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sport;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSportsTest extends TestCase
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

    private function actingAsSportsManager(): Staff
    {
        $staff = $this->staffWithPermissions(['sports.manage']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $sport = Sport::factory()->create();

        $endpoints = [
            ['getJson', '/api/v1/admin/sports'],
            ['postJson', '/api/v1/admin/sports'],
            ['getJson', "/api/v1/admin/sports/{$sport->id}"],
            ['putJson', "/api/v1/admin/sports/{$sport->id}"],
            ['deleteJson', "/api/v1/admin/sports/{$sport->id}"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertUnauthorized();
        }

        Sanctum::actingAs(User::factory()->create());
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }

        Sanctum::actingAs($this->staffWithPermissions(['facilities.manage']));
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    public function test_sport_crud_and_audit(): void
    {
        $this->actingAsSportsManager();

        $response = $this->postJson('/api/v1/admin/sports', [
            'name_ar' => 'كرة القدم',
            'name_en' => 'Football',
            'icon_url' => 'https://example.com/football.png',
        ])
            ->assertCreated()
            ->assertJsonPath('message', __('admin_sports.sport_created', [], 'ar'))
            ->assertJsonPath('data.name_ar', 'كرة القدم')
            ->assertJsonPath('data.name_en', 'Football')
            ->assertJsonPath('data.icon_url', 'https://example.com/football.png')
            ->assertJsonPath('data.is_active', true);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('sports', ['id' => $id, 'name_ar' => 'كرة القدم']);
        $this->assertTrue(AuditLog::query()->where('action', 'sport.created')->where('entity_id', $id)->exists());

        $this->getJson("/api/v1/admin/sports/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.name_ar', 'كرة القدم');

        $this->putJson("/api/v1/admin/sports/{$id}", ['name_en' => 'Soccer', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('message', __('admin_sports.sport_updated', [], 'ar'))
            ->assertJsonPath('data.name_en', 'Soccer')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.name_ar', 'كرة القدم');

        $updateLog = AuditLog::query()->where('action', 'sport.updated')->where('entity_id', $id)->firstOrFail();
        $this->assertEquals(['name_en' => 'Football', 'is_active' => true], $updateLog->before);
        $this->assertEquals(['name_en' => 'Soccer', 'is_active' => false], $updateLog->after);

        $this->deleteJson("/api/v1/admin/sports/{$id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_sports.sport_deleted', [], 'ar'));
        $this->assertDatabaseMissing('sports', ['id' => $id]);
        $this->assertTrue(AuditLog::query()->where('action', 'sport.deleted')->where('entity_id', $id)->exists());
    }

    public function test_update_without_changes_writes_no_audit_row(): void
    {
        $this->actingAsSportsManager();
        $sport = Sport::factory()->create(['name_ar' => 'سباحة', 'name_en' => 'Swimming']);

        $this->putJson("/api/v1/admin/sports/{$sport->id}", ['name_en' => 'Swimming', 'name_ar' => 'سباحة'])
            ->assertOk();

        $this->assertFalse(AuditLog::query()->where('action', 'sport.updated')->where('entity_id', $sport->id)->exists());
    }

    public function test_update_may_keep_its_own_arabic_name_but_not_take_another_sport_name(): void
    {
        $this->actingAsSportsManager();
        $sport = Sport::factory()->create(['name_ar' => 'تنس']);
        Sport::factory()->create(['name_ar' => 'بادل']);

        $this->putJson("/api/v1/admin/sports/{$sport->id}", ['name_ar' => 'تنس'])
            ->assertOk();

        $this->putJson("/api/v1/admin/sports/{$sport->id}", ['name_ar' => 'بادل'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name_ar.0', __('admin_sports.validation.name_ar_taken', [], 'ar'));
    }

    public function test_sport_index_is_ordered_by_id_and_filtered_by_active_state(): void
    {
        $this->actingAsSportsManager();

        $first = Sport::factory()->create(['name_ar' => 'أ', 'is_active' => true]);
        $second = Sport::factory()->create(['name_ar' => 'ب', 'is_active' => false]);
        $third = Sport::factory()->create(['name_ar' => 'ج', 'is_active' => true]);

        $this->getJson('/api/v1/admin/sports')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.2.id', $third->id)
            ->assertJsonStructure(['data' => [['id', 'name_ar', 'name_en', 'icon_url', 'is_active']]]);

        $this->getJson('/api/v1/admin/sports?is_active=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $third->id);

        $this->getJson('/api/v1/admin/sports?is_active=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.is_active', false);

        $this->getJson('/api/v1/admin/sports?is_active=maybe')
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_active.0', __('admin_sports.validation.is_active_invalid', [], 'ar'));
    }

    public function test_sport_validation_errors_in_both_languages(): void
    {
        $this->actingAsSportsManager();
        Sport::factory()->create(['name_ar' => 'كرة السلة']);

        $this->postJson('/api/v1/admin/sports', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name_ar.0', __('admin_sports.validation.name_ar_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/sports', [
                'name_ar' => 'كرة السلة',
                'name_en' => str_repeat('a', 101),
                'icon_url' => 'not-a-url',
                'is_active' => 'maybe',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name_ar.0', 'A sport with this Arabic name already exists.')
            ->assertJsonPath('errors.name_en.0', 'The English name must be text of at most 100 characters.')
            ->assertJsonPath('errors.icon_url.0', 'The icon URL must be a valid URL of at most 500 characters.')
            ->assertJsonPath('errors.is_active.0', 'The active flag must be true or false.');

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/sports', ['name_ar' => str_repeat('a', 101)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name_ar.0', 'The Arabic name must be text of at most 100 characters.');

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/sports', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name_ar.0', 'The Arabic name is required.');
    }

    public function test_missing_sport_returns_not_found(): void
    {
        $this->actingAsSportsManager();

        $this->getJson('/api/v1/admin/sports/9999')->assertNotFound();
        $this->putJson('/api/v1/admin/sports/9999', ['name_ar' => 'x'])->assertNotFound();
        $this->deleteJson('/api/v1/admin/sports/9999')->assertNotFound();
    }

    public function test_deleting_sport_linked_to_bookable_unit_returns_conflict(): void
    {
        $this->actingAsSportsManager();
        $sport = Sport::factory()->create(['name_ar' => 'طائرة']);
        $unit = BookableUnit::factory()->create();
        $unit->sports()->attach($sport);

        $this->deleteJson("/api/v1/admin/sports/{$sport->id}")
            ->assertConflict()
            ->assertJsonPath('message', __('admin_sports.sport_in_use', [], 'ar'));

        $this->assertDatabaseHas('sports', ['id' => $sport->id]);
        $this->assertDatabaseHas('unit_sports', ['unit_id' => $unit->id, 'sport_id' => $sport->id]);
        $this->assertFalse(AuditLog::query()->where('action', 'sport.deleted')->where('entity_id', $sport->id)->exists());
    }

    public function test_deleting_unused_sport_removes_it(): void
    {
        $this->actingAsSportsManager();
        $sport = Sport::factory()->create();

        $this->deleteJson("/api/v1/admin/sports/{$sport->id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_sports.sport_deleted', [], 'ar'));

        $this->assertDatabaseMissing('sports', ['id' => $sport->id]);
        $this->assertTrue(AuditLog::query()->where('action', 'sport.deleted')->where('entity_id', $sport->id)->exists());
    }
}
