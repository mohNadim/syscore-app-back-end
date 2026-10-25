<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\News;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminNewsTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title_ar' => 'خبر تجريبي',
            'title_en' => 'Sample news',
            'body_ar' => 'نص الخبر بالعربية.',
            'body_en' => 'News body in English.',
            'cover_url' => 'https://example.com/cover.jpg',
            'is_published' => true,
            'published_at' => '2026-11-01T10:00:00+03:00',
        ], $overrides);
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $news = News::factory()->create();

        $endpoints = [
            ['getJson', '/api/v1/admin/news'],
            ['postJson', '/api/v1/admin/news'],
            ['getJson', "/api/v1/admin/news/{$news->id}"],
            ['putJson', "/api/v1/admin/news/{$news->id}"],
            ['deleteJson', "/api/v1/admin/news/{$news->id}"],
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

    public function test_create_stores_article_with_authenticated_staff_as_creator_and_audits(): void
    {
        $staff = $this->actingAsContentManager();

        $response = $this->postJson('/api/v1/admin/news', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('message', __('admin_news.news_created', [], 'ar'))
            ->assertJsonPath('data.title_ar', 'خبر تجريبي')
            ->assertJsonPath('data.title_en', 'Sample news')
            ->assertJsonPath('data.is_published', true)
            ->assertJsonPath('data.created_by_staff_id', $staff->id);

        $news = News::query()->findOrFail($response->json('data.id'));

        $this->assertSame($staff->id, $news->created_by_staff_id);
        $this->assertSame('2026-11-01 07:00:00', $news->published_at->utc()->format('Y-m-d H:i:s'));
        $this->assertTrue(AuditLog::query()->where('action', 'news.created')->where('entity_type', 'news')->where('entity_id', $news->id)->exists());
    }

    public function test_create_defaults_to_unpublished_without_published_at(): void
    {
        $this->actingAsContentManager();

        $this->postJson('/api/v1/admin/news', [
            'title_ar' => 'خبر',
            'title_en' => 'News',
            'body_ar' => 'نص',
            'body_en' => 'Body',
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.cover_url', null);
    }

    public function test_create_missing_fields_return_arabic_messages(): void
    {
        $this->actingAsContentManager();

        $this->postJson('/api/v1/admin/news', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.title_ar.0', __('admin_news.validation.title_ar_required', [], 'ar'))
            ->assertJsonPath('errors.title_en.0', __('admin_news.validation.title_en_required', [], 'ar'))
            ->assertJsonPath('errors.body_ar.0', __('admin_news.validation.body_ar_required', [], 'ar'))
            ->assertJsonPath('errors.body_en.0', __('admin_news.validation.body_en_required', [], 'ar'));
    }

    public function test_create_missing_fields_return_english_messages(): void
    {
        $this->actingAsContentManager();

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/news', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.title_ar.0', __('admin_news.validation.title_ar_required', [], 'en'))
            ->assertJsonPath('errors.title_en.0', __('admin_news.validation.title_en_required', [], 'en'))
            ->assertJsonPath('errors.body_ar.0', __('admin_news.validation.body_ar_required', [], 'en'))
            ->assertJsonPath('errors.body_en.0', __('admin_news.validation.body_en_required', [], 'en'));
    }

    public function test_create_rejects_wrong_types_and_invalid_values(): void
    {
        $this->actingAsContentManager();

        $this->postJson('/api/v1/admin/news', $this->validPayload([
            'title_ar' => ['not', 'a', 'string'],
            'title_en' => 12345,
            'body_ar' => ['x'],
            'body_en' => false,
            'cover_url' => 'not-a-url',
            'is_published' => 'maybe',
            'published_at' => 'not-a-date',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.title_ar.0', __('admin_news.validation.title_ar_invalid', [], 'ar'))
            ->assertJsonPath('errors.title_en.0', __('admin_news.validation.title_en_invalid', [], 'ar'))
            ->assertJsonPath('errors.body_ar.0', __('admin_news.validation.body_ar_invalid', [], 'ar'))
            ->assertJsonPath('errors.body_en.0', __('admin_news.validation.body_en_invalid', [], 'ar'))
            ->assertJsonPath('errors.cover_url.0', __('admin_news.validation.cover_url_invalid', [], 'ar'))
            ->assertJsonPath('errors.is_published.0', __('admin_news.validation.is_published_invalid', [], 'ar'))
            ->assertJsonPath('errors.published_at.0', __('admin_news.validation.published_at_invalid', [], 'ar'));
    }

    public function test_create_respects_length_boundaries(): void
    {
        $this->actingAsContentManager();

        $this->postJson('/api/v1/admin/news', $this->validPayload([
            'title_ar' => str_repeat('ع', 200),
            'title_en' => str_repeat('a', 200),
            'body_ar' => str_repeat('ن', 20000),
            'body_en' => str_repeat('b', 20000),
        ]))->assertCreated();

        $this->postJson('/api/v1/admin/news', $this->validPayload(['title_en' => str_repeat('a', 201)]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.title_en.0', __('admin_news.validation.title_en_invalid', [], 'ar'));

        $this->postJson('/api/v1/admin/news', $this->validPayload(['body_en' => str_repeat('b', 20001)]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.body_en.0', __('admin_news.validation.body_en_invalid', [], 'ar'));

        $this->postJson('/api/v1/admin/news', $this->validPayload(['cover_url' => 'https://example.com/'.str_repeat('a', 500)]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.cover_url.0', __('admin_news.validation.cover_url_invalid', [], 'ar'));
    }

    public function test_naive_published_at_is_read_in_display_timezone(): void
    {
        $this->actingAsContentManager();

        $response = $this->postJson('/api/v1/admin/news', $this->validPayload(['published_at' => '2026-11-01 10:00:00']))
            ->assertCreated();

        $this->assertSame('2026-11-01 07:00:00', News::query()->findOrFail($response->json('data.id'))->published_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_index_paginates_filters_by_published_state_and_validates_per_page(): void
    {
        $this->actingAsContentManager();

        News::factory()->count(3)->create(['is_published' => true]);
        News::factory()->count(2)->create(['is_published' => false]);

        $this->getJson('/api/v1/admin/news')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->getJson('/api/v1/admin/news?is_published=0')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.is_published', false);

        $this->getJson('/api/v1/admin/news?is_published=1&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);

        $this->getJson('/api/v1/admin/news?per_page=0')
            ->assertUnprocessable()
            ->assertJsonPath('errors.per_page.0', __('admin_news.validation.per_page_invalid', [], 'ar'));

        $this->getJson('/api/v1/admin/news?per_page=51')
            ->assertUnprocessable()
            ->assertJsonPath('errors.per_page.0', __('admin_news.validation.per_page_invalid', [], 'ar'));

        $this->getJson('/api/v1/admin/news?is_published=maybe')
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_published.0', __('admin_news.validation.is_published_invalid', [], 'ar'));

        $this->getJson('/api/v1/admin/news?per_page=50')->assertOk();
    }

    public function test_show_returns_unpublished_article_with_all_fields_and_404_when_missing(): void
    {
        $this->actingAsContentManager();

        $news = News::factory()->create(['is_published' => false, 'published_at' => null]);

        $this->getJson("/api/v1/admin/news/{$news->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $news->id)
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.published_at', null)
            ->assertJsonStructure(['data' => ['title_ar', 'title_en', 'body_ar', 'body_en', 'cover_url', 'is_published', 'published_at', 'created_by_staff_id', 'created_at']]);

        $this->getJson('/api/v1/admin/news/999999')->assertNotFound();
    }

    public function test_update_changes_only_sent_fields_and_audits(): void
    {
        $this->actingAsContentManager();

        $news = News::factory()->create(['title_ar' => 'قديم', 'title_en' => 'Old', 'is_published' => false]);

        $this->putJson("/api/v1/admin/news/{$news->id}", ['title_en' => 'New', 'is_published' => true])
            ->assertOk()
            ->assertJsonPath('message', __('admin_news.news_updated', [], 'ar'))
            ->assertJsonPath('data.title_en', 'New')
            ->assertJsonPath('data.title_ar', 'قديم')
            ->assertJsonPath('data.is_published', true);

        $audit = AuditLog::query()->where('action', 'news.updated')->where('entity_id', $news->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame('Old', $audit->before['title_en']);
        $this->assertSame('New', $audit->after['title_en']);
    }

    public function test_update_validates_fields_and_returns_404_when_missing(): void
    {
        $this->actingAsContentManager();

        $news = News::factory()->create();

        $this->withHeader('Accept-Language', 'en')
            ->putJson("/api/v1/admin/news/{$news->id}", ['title_ar' => '', 'published_at' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.title_ar.0', __('admin_news.validation.title_ar_required', [], 'en'))
            ->assertJsonPath('errors.published_at.0', __('admin_news.validation.published_at_invalid', [], 'en'));

        $this->putJson('/api/v1/admin/news/999999', ['title_ar' => 'x'])->assertNotFound();
    }

    public function test_destroy_deletes_article_and_audits(): void
    {
        $this->actingAsContentManager();

        $news = News::factory()->create();

        $this->deleteJson("/api/v1/admin/news/{$news->id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_news.news_deleted', [], 'ar'));

        $this->assertDatabaseMissing('news', ['id' => $news->id]);
        $this->assertTrue(AuditLog::query()->where('action', 'news.deleted')->where('entity_id', $news->id)->exists());

        $this->deleteJson("/api/v1/admin/news/{$news->id}")->assertNotFound();
    }
}
