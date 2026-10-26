<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\GalleryAlbum;
use App\Models\GalleryPhoto;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function actingAsContentManager(): Staff
    {
        $staff = $this->staffWithPermissions(['content.manage']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $album = GalleryAlbum::factory()->create();
        $photo = GalleryPhoto::factory()->create(['album_id' => $album->id]);

        $endpoints = [
            ['getJson', '/api/v1/admin/gallery/albums'],
            ['postJson', '/api/v1/admin/gallery/albums'],
            ['getJson', "/api/v1/admin/gallery/albums/{$album->id}"],
            ['putJson', "/api/v1/admin/gallery/albums/{$album->id}"],
            ['deleteJson', "/api/v1/admin/gallery/albums/{$album->id}"],
            ['postJson', "/api/v1/admin/gallery/albums/{$album->id}/photos"],
            ['putJson', "/api/v1/admin/gallery/photos/{$photo->id}"],
            ['deleteJson', "/api/v1/admin/gallery/photos/{$photo->id}"],
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

    public function test_create_album_with_defaults_and_audits(): void
    {
        $this->actingAsContentManager();

        $response = $this->postJson('/api/v1/admin/gallery/albums', [
            'title_ar' => 'ألبوم الصيف',
            'title_en' => 'Summer album',
            'sort_order' => 3,
        ])
            ->assertCreated()
            ->assertJsonPath('message', __('admin_gallery.album_created', [], 'ar'))
            ->assertJsonPath('data.title_ar', 'ألبوم الصيف')
            ->assertJsonPath('data.is_published', true)
            ->assertJsonPath('data.photos_count', 0)
            ->assertJsonPath('data.cover_url', null);

        $this->assertDatabaseHas('gallery_albums', ['id' => $response->json('data.id'), 'sort_order' => 3]);
        $this->assertTrue(AuditLog::query()->where('action', 'gallery_album.created')->where('entity_id', $response->json('data.id'))->exists());
    }

    public function test_create_album_title_en_is_optional(): void
    {
        $this->actingAsContentManager();

        $this->postJson('/api/v1/admin/gallery/albums', ['title_ar' => 'ألبوم', 'title_en' => null, 'is_published' => false])
            ->assertCreated()
            ->assertJsonPath('data.title_en', null)
            ->assertJsonPath('data.is_published', false);
    }

    public function test_create_album_missing_fields_return_arabic_and_english_messages(): void
    {
        $this->actingAsContentManager();

        $this->postJson('/api/v1/admin/gallery/albums', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.title_ar.0', __('admin_gallery.validation.title_ar_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/gallery/albums', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.title_ar.0', __('admin_gallery.validation.title_ar_required', [], 'en'));
    }

    public function test_create_album_rejects_wrong_types_and_boundaries(): void
    {
        $this->actingAsContentManager();

        $this->postJson('/api/v1/admin/gallery/albums', [
            'title_ar' => ['x'],
            'title_en' => 5,
            'sort_order' => 'first',
            'is_published' => 'maybe',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.title_ar.0', __('admin_gallery.validation.title_ar_invalid', [], 'ar'))
            ->assertJsonPath('errors.title_en.0', __('admin_gallery.validation.title_en_invalid', [], 'ar'))
            ->assertJsonPath('errors.sort_order.0', __('admin_gallery.validation.sort_order_invalid', [], 'ar'))
            ->assertJsonPath('errors.is_published.0', __('admin_gallery.validation.is_published_invalid', [], 'ar'));

        $this->postJson('/api/v1/admin/gallery/albums', ['title_ar' => str_repeat('ع', 200), 'sort_order' => 65535])->assertCreated();
        $this->postJson('/api/v1/admin/gallery/albums', ['title_ar' => str_repeat('ع', 201)])
            ->assertJsonPath('errors.title_ar.0', __('admin_gallery.validation.title_ar_invalid', [], 'ar'));
        $this->postJson('/api/v1/admin/gallery/albums', ['title_ar' => 'x', 'sort_order' => 65536])
            ->assertJsonPath('errors.sort_order.0', __('admin_gallery.validation.sort_order_invalid', [], 'ar'));
        $this->postJson('/api/v1/admin/gallery/albums', ['title_ar' => 'x', 'sort_order' => -1])
            ->assertJsonPath('errors.sort_order.0', __('admin_gallery.validation.sort_order_invalid', [], 'ar'));
    }

    public function test_index_paginates_with_photo_counts_and_validates_per_page(): void
    {
        $this->actingAsContentManager();

        $first = GalleryAlbum::factory()->create(['sort_order' => 2]);
        $second = GalleryAlbum::factory()->create(['sort_order' => 1]);
        GalleryPhoto::factory()->count(2)->create(['album_id' => $second->id]);

        $this->getJson('/api/v1/admin/gallery/albums?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.photos_count', 2)
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/admin/gallery/albums')
            ->assertOk()
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonPath('data.1.photos_count', 0);

        $this->getJson('/api/v1/admin/gallery/albums?per_page=0')
            ->assertUnprocessable()
            ->assertJsonPath('errors.per_page.0', __('admin_gallery.validation.per_page_invalid', [], 'ar'));

        $this->getJson('/api/v1/admin/gallery/albums?per_page=51')
            ->assertUnprocessable()
            ->assertJsonPath('errors.per_page.0', __('admin_gallery.validation.per_page_invalid', [], 'ar'));
    }

    public function test_show_returns_album_with_photos_in_order_and_404_when_missing(): void
    {
        $this->actingAsContentManager();

        $album = GalleryAlbum::factory()->create();
        GalleryPhoto::factory()->create(['album_id' => $album->id, 'sort_order' => 5, 'image_url' => 'https://example.com/b.jpg']);
        GalleryPhoto::factory()->create(['album_id' => $album->id, 'sort_order' => 1, 'image_url' => 'https://example.com/a.jpg']);

        $this->getJson("/api/v1/admin/gallery/albums/{$album->id}")
            ->assertOk()
            ->assertJsonPath('data.photos_count', 2)
            ->assertJsonPath('data.cover_url', 'https://example.com/a.jpg')
            ->assertJsonPath('data.photos.0.image_url', 'https://example.com/a.jpg')
            ->assertJsonPath('data.photos.1.image_url', 'https://example.com/b.jpg');

        $this->getJson('/api/v1/admin/gallery/albums/999999')->assertNotFound();
    }

    public function test_update_album_changes_only_sent_fields_and_audits(): void
    {
        $this->actingAsContentManager();

        $album = GalleryAlbum::factory()->create(['title_ar' => 'قديم', 'is_published' => true]);

        $this->putJson("/api/v1/admin/gallery/albums/{$album->id}", ['is_published' => false, 'sort_order' => 4])
            ->assertOk()
            ->assertJsonPath('message', __('admin_gallery.album_updated', [], 'ar'))
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.title_ar', 'قديم')
            ->assertJsonPath('data.sort_order', 4);

        $audit = AuditLog::query()->where('action', 'gallery_album.updated')->where('entity_id', $album->id)->first();
        $this->assertNotNull($audit);
        $this->assertTrue((bool) $audit->before['is_published']);
        $this->assertFalse((bool) $audit->after['is_published']);
    }

    public function test_update_album_validates_fields_and_returns_404_when_missing(): void
    {
        $this->actingAsContentManager();

        $album = GalleryAlbum::factory()->create();

        $this->withHeader('Accept-Language', 'en')
            ->putJson("/api/v1/admin/gallery/albums/{$album->id}", ['title_ar' => '', 'sort_order' => 70000])
            ->assertUnprocessable()
            ->assertJsonPath('errors.title_ar.0', __('admin_gallery.validation.title_ar_required', [], 'en'))
            ->assertJsonPath('errors.sort_order.0', __('admin_gallery.validation.sort_order_invalid', [], 'en'));

        $this->putJson('/api/v1/admin/gallery/albums/999999', ['title_ar' => 'x'])->assertNotFound();
    }

    public function test_delete_album_removes_its_photos_and_audits(): void
    {
        $this->actingAsContentManager();

        $album = GalleryAlbum::factory()->create();
        $photos = GalleryPhoto::factory()->count(2)->create(['album_id' => $album->id]);
        $keptAlbum = GalleryAlbum::factory()->create();
        $keptPhoto = GalleryPhoto::factory()->create(['album_id' => $keptAlbum->id]);

        $this->deleteJson("/api/v1/admin/gallery/albums/{$album->id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_gallery.album_deleted', [], 'ar'));

        $this->assertDatabaseMissing('gallery_albums', ['id' => $album->id]);
        $this->assertDatabaseMissing('gallery_photos', ['id' => $photos[0]->id]);
        $this->assertDatabaseMissing('gallery_photos', ['id' => $photos[1]->id]);
        $this->assertDatabaseHas('gallery_photos', ['id' => $keptPhoto->id]);
        $this->assertTrue(AuditLog::query()->where('action', 'gallery_album.deleted')->where('entity_id', $album->id)->exists());
        $this->assertSame(2, AuditLog::query()->where('action', 'gallery_photo.deleted')->count());

        $this->deleteJson("/api/v1/admin/gallery/albums/{$album->id}")->assertNotFound();
    }

    public function test_store_photo_attaches_to_album_and_validates_fields(): void
    {
        $this->actingAsContentManager();

        $album = GalleryAlbum::factory()->create();

        $response = $this->postJson("/api/v1/admin/gallery/albums/{$album->id}/photos", [
            'image_url' => 'https://example.com/photo.jpg',
            'caption_ar' => 'تعليق',
            'caption_en' => 'Caption',
            'sort_order' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('message', __('admin_gallery.photo_created', [], 'ar'))
            ->assertJsonPath('data.album_id', $album->id)
            ->assertJsonPath('data.caption_en', 'Caption');

        $this->assertTrue(AuditLog::query()->where('action', 'gallery_photo.created')->where('entity_id', $response->json('data.id'))->exists());

        $this->postJson("/api/v1/admin/gallery/albums/{$album->id}/photos", ['caption_ar' => 'بدون صورة'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.image_url.0', __('admin_gallery.validation.image_url_required', [], 'ar'));

        $this->postJson("/api/v1/admin/gallery/albums/{$album->id}/photos", [
            'image_url' => ['not', 'text'],
            'caption_ar' => str_repeat('ت', 256),
            'caption_en' => 7,
            'sort_order' => 'x',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.image_url.0', __('admin_gallery.validation.image_url_invalid', [], 'ar'))
            ->assertJsonPath('errors.caption_ar.0', __('admin_gallery.validation.caption_ar_invalid', [], 'ar'))
            ->assertJsonPath('errors.caption_en.0', __('admin_gallery.validation.caption_en_invalid', [], 'ar'))
            ->assertJsonPath('errors.sort_order.0', __('admin_gallery.validation.sort_order_invalid', [], 'ar'));

        $this->postJson("/api/v1/admin/gallery/albums/{$album->id}/photos", ['image_url' => 'ftp://example.com/a.jpg'])
            ->assertJsonPath('errors.image_url.0', __('admin_gallery.validation.image_url_invalid', [], 'ar'));

        $this->postJson("/api/v1/admin/gallery/albums/{$album->id}/photos", [
            'image_url' => 'https://example.com/'.str_repeat('a', 500),
        ])->assertJsonPath('errors.image_url.0', __('admin_gallery.validation.image_url_invalid', [], 'ar'));

        $this->postJson('/api/v1/admin/gallery/albums/999999/photos', ['image_url' => 'https://example.com/a.jpg'])->assertNotFound();

        $this->withHeader('Accept-Language', 'en')
            ->postJson("/api/v1/admin/gallery/albums/{$album->id}/photos", [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.image_url.0', __('admin_gallery.validation.image_url_required', [], 'en'));
    }

    public function test_update_photo_changes_only_sent_fields_and_validates(): void
    {
        $this->actingAsContentManager();

        $photo = GalleryPhoto::factory()->create(['caption_en' => 'Old', 'sort_order' => 0]);

        $this->putJson("/api/v1/admin/gallery/photos/{$photo->id}", ['caption_en' => 'New', 'sort_order' => 9])
            ->assertOk()
            ->assertJsonPath('message', __('admin_gallery.photo_updated', [], 'ar'))
            ->assertJsonPath('data.caption_en', 'New')
            ->assertJsonPath('data.image_url', $photo->image_url)
            ->assertJsonPath('data.sort_order', 9);

        $audit = AuditLog::query()->where('action', 'gallery_photo.updated')->where('entity_id', $photo->id)->first();
        $this->assertSame('Old', $audit->before['caption_en']);

        $this->putJson("/api/v1/admin/gallery/photos/{$photo->id}", ['image_url' => 'javascript:alert(1)'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.image_url.0', __('admin_gallery.validation.image_url_invalid', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->putJson("/api/v1/admin/gallery/photos/{$photo->id}", ['image_url' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.image_url.0', __('admin_gallery.validation.image_url_required', [], 'en'));

        $this->putJson('/api/v1/admin/gallery/photos/999999', ['caption_en' => 'x'])->assertNotFound();
    }

    public function test_delete_photo_and_audits(): void
    {
        $this->actingAsContentManager();

        $photo = GalleryPhoto::factory()->create();

        $this->deleteJson("/api/v1/admin/gallery/photos/{$photo->id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_gallery.photo_deleted', [], 'ar'));

        $this->assertDatabaseMissing('gallery_photos', ['id' => $photo->id]);
        $this->assertTrue(AuditLog::query()->where('action', 'gallery_photo.deleted')->where('entity_id', $photo->id)->exists());

        $this->deleteJson("/api/v1/admin/gallery/photos/{$photo->id}")->assertNotFound();
    }
}
