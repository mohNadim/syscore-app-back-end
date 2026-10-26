<?php

namespace Tests\Feature;

use App\Models\Academy;
use App\Models\AcademyGroup;
use App\Models\AuditLog;
use App\Models\Coach;
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

class AdminCoachesTest extends TestCase
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

    private function actingAsCoachesManager(): Staff
    {
        $staff = $this->staffWithPermissions(['coaches.manage']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    private function pathOf(string $url): string
    {
        return ltrim(substr($url, strlen(rtrim(Storage::disk('public')->url(''), '/'))), '/');
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $coach = Coach::factory()->create();

        $endpoints = [
            ['getJson', '/api/v1/admin/coaches'],
            ['postJson', '/api/v1/admin/coaches'],
            ['getJson', "/api/v1/admin/coaches/{$coach->id}"],
            ['putJson', "/api/v1/admin/coaches/{$coach->id}"],
            ['deleteJson', "/api/v1/admin/coaches/{$coach->id}"],
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

    public function test_coach_crud_with_json_and_audit(): void
    {
        $this->actingAsCoachesManager();
        $sport = Sport::factory()->create();

        $response = $this->postJson('/api/v1/admin/coaches', [
            'full_name_ar' => 'أحمد',
            'full_name_en' => 'Ahmad',
            'bio_ar' => 'نبذة',
            'sport_id' => $sport->id,
            'sort_order' => 3,
        ])
            ->assertCreated()
            ->assertJsonPath('message', __('admin_coaches.coach_created', [], 'ar'))
            ->assertJsonPath('data.full_name_ar', 'أحمد')
            ->assertJsonPath('data.sport.id', $sport->id)
            ->assertJsonPath('data.photo_url', null)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.sort_order', 3);

        $id = $response->json('data.id');
        $this->assertTrue(AuditLog::query()->where('action', 'coach.created')->where('entity_id', $id)->exists());

        $this->getJson("/api/v1/admin/coaches/{$id}")->assertOk()->assertJsonPath('data.id', $id);

        $this->putJson("/api/v1/admin/coaches/{$id}", ['full_name_en' => 'Ahmad B', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('message', __('admin_coaches.coach_updated', [], 'ar'))
            ->assertJsonPath('data.full_name_en', 'Ahmad B')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.full_name_ar', 'أحمد');
        $this->assertTrue(AuditLog::query()->where('action', 'coach.updated')->where('entity_id', $id)->exists());

        $this->deleteJson("/api/v1/admin/coaches/{$id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_coaches.coach_deleted', [], 'ar'));
        $this->assertDatabaseMissing('coaches', ['id' => $id]);
        $this->assertTrue(AuditLog::query()->where('action', 'coach.deleted')->where('entity_id', $id)->exists());
    }

    public function test_index_is_ordered_and_filtered(): void
    {
        $this->actingAsCoachesManager();
        $football = Sport::factory()->create();
        $tennis = Sport::factory()->create();

        $b = Coach::factory()->create(['sort_order' => 2, 'sport_id' => $football->id]);
        $a = Coach::factory()->create(['sort_order' => 1, 'sport_id' => $tennis->id, 'is_active' => false]);
        $c = Coach::factory()->create(['sort_order' => 2, 'sport_id' => $football->id, 'is_active' => false]);

        $this->getJson('/api/v1/admin/coaches')
            ->assertOk()
            ->assertJsonPath('data.0.id', $a->id)
            ->assertJsonPath('data.1.id', $b->id)
            ->assertJsonPath('data.2.id', $c->id);

        $this->getJson("/api/v1/admin/coaches?sport_id={$football->id}")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/admin/coaches?is_active=0')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/admin/coaches?sport_id={$football->id}&is_active=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $b->id);

        $this->getJson('/api/v1/admin/coaches?sport_id=abc&is_active=maybe')
            ->assertUnprocessable()
            ->assertJsonPath('errors.sport_id.0', __('admin_coaches.validation.sport_id_invalid', [], 'ar'))
            ->assertJsonPath('errors.is_active.0', __('admin_coaches.validation.is_active_invalid', [], 'ar'));
    }

    public function test_photo_upload_replace_and_remove(): void
    {
        $this->actingAsCoachesManager();

        $response = $this->post('/api/v1/admin/coaches', [
            'full_name_ar' => 'سامر',
            'photo' => UploadedFile::fake()->image('a.png', 100, 100),
        ], ['Accept' => 'application/json'])->assertCreated();

        $id = $response->json('data.id');
        $firstUrl = $response->json('data.photo_url');
        $firstPath = $this->pathOf($firstUrl);
        $this->assertStringStartsWith(Storage::disk('public')->url('coaches/'), $firstUrl);
        Storage::disk('public')->assertExists($firstPath);

        $second = $this->post("/api/v1/admin/coaches/{$id}", [
            '_method' => 'PUT',
            'full_name_en' => 'Samer',
            'photo' => UploadedFile::fake()->image('b.jpg', 100, 100),
        ], ['Accept' => 'application/json'])->assertOk();

        $secondPath = $this->pathOf($second->json('data.photo_url'));
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
        $this->assertSame('Samer', $second->json('data.full_name_en'));

        $this->post("/api/v1/admin/coaches/{$id}", ['_method' => 'PUT', 'remove_photo' => '1'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.photo_url', null);
        Storage::disk('public')->assertMissing($secondPath);
        $this->assertNull(Coach::find($id)->photo_url);
    }

    public function test_photo_validation(): void
    {
        $this->actingAsCoachesManager();

        $this->post('/api/v1/admin/coaches', [
            'full_name_ar' => 'x',
            'photo' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.photo.0', __('admin_coaches.validation.photo_invalid', [], 'ar'));

        $this->post('/api/v1/admin/coaches', [
            'full_name_ar' => 'x',
            'photo' => UploadedFile::fake()->image('big.png')->size(3000),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

        $this->assertDatabaseCount('coaches', 0);
    }

    public function test_validation_errors_in_both_languages(): void
    {
        $this->actingAsCoachesManager();

        $this->postJson('/api/v1/admin/coaches', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.full_name_ar.0', __('admin_coaches.validation.full_name_ar_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/coaches', [
                'full_name_ar' => str_repeat('a', 151),
                'full_name_en' => str_repeat('a', 151),
                'bio_ar' => str_repeat('a', 5001),
                'sport_id' => 99999,
                'is_active' => 'maybe',
                'sort_order' => 70000,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.full_name_ar.0', 'The Arabic name must be text of at most 150 characters.')
            ->assertJsonPath('errors.full_name_en.0', 'The English name must be text of at most 150 characters.')
            ->assertJsonPath('errors.bio_ar.0', 'The bio must be text of at most 5000 characters.')
            ->assertJsonPath('errors.sport_id.0', 'The sport must be an existing sport.')
            ->assertJsonPath('errors.is_active.0', 'The active flag must be true or false.')
            ->assertJsonPath('errors.sort_order.0', 'The sort order must be a whole number between 0 and 65535.');

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/coaches', [])
            ->assertJsonPath('errors.full_name_ar.0', 'The Arabic name is required.');
    }

    public function test_missing_coach_returns_not_found(): void
    {
        $this->actingAsCoachesManager();

        $this->getJson('/api/v1/admin/coaches/9999')->assertNotFound();
        $this->putJson('/api/v1/admin/coaches/9999', [])->assertNotFound();
        $this->deleteJson('/api/v1/admin/coaches/9999')->assertNotFound();
    }

    public function test_deleting_coach_used_by_a_group_conflicts_and_keeps_photo(): void
    {
        $this->actingAsCoachesManager();
        $url = app(PublicImageStore::class)->store(UploadedFile::fake()->image('c.png'), 'coaches');
        $coach = Coach::factory()->create(['photo_url' => $url]);
        AcademyGroup::factory()->create(['academy_id' => Academy::factory(), 'coach_id' => $coach->id]);

        $this->deleteJson("/api/v1/admin/coaches/{$coach->id}")
            ->assertConflict()
            ->assertJsonPath('message', __('admin_coaches.coach_in_use', [], 'ar'));

        $this->assertDatabaseHas('coaches', ['id' => $coach->id]);
        Storage::disk('public')->assertExists($this->pathOf($url));
        $this->assertFalse(AuditLog::query()->where('action', 'coach.deleted')->exists());
    }

    public function test_deleting_coach_removes_stored_photo(): void
    {
        $this->actingAsCoachesManager();
        $url = app(PublicImageStore::class)->store(UploadedFile::fake()->image('c.png'), 'coaches');
        $coach = Coach::factory()->create(['photo_url' => $url]);

        $this->deleteJson("/api/v1/admin/coaches/{$coach->id}")->assertOk();

        Storage::disk('public')->assertMissing($this->pathOf($url));
    }

    public function test_image_store_leaves_external_urls_alone(): void
    {
        $store = app(PublicImageStore::class);
        Storage::disk('public')->put('coaches/keep.png', 'x');

        $store->delete('https://example.com/coaches/keep.png');
        $store->delete(null);
        Storage::disk('public')->assertExists('coaches/keep.png');

        $store->delete(Storage::disk('public')->url('coaches/keep.png'));
        Storage::disk('public')->assertMissing('coaches/keep.png');
    }
}
