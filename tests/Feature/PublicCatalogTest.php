<?php

namespace Tests\Feature;

use App\Models\Academy;
use App\Models\AcademyGroup;
use App\Models\BookableUnit;
use App\Models\Coach;
use App\Models\Facility;
use App\Models\GalleryAlbum;
use App\Models\GalleryPhoto;
use App\Models\GroupSchedule;
use App\Models\News;
use App\Models\Setting;
use App\Models\Space;
use App\Models\Sport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
    }

    public function test_facilities_list_only_active_rows_with_units_sports_and_spaces(): void
    {
        $facility = Facility::factory()->create(['sort_order' => 1]);
        $first = Facility::factory()->create(['sort_order' => 0]);
        Facility::factory()->create(['is_active' => false]);
        $space = Space::factory()->create(['facility_id' => $facility->id]);
        $unit = BookableUnit::factory()->create(['facility_id' => $facility->id]);
        BookableUnit::factory()->inactive()->create(['facility_id' => $facility->id]);
        $unit->spaces()->attach($space);
        $unit->sports()->attach([Sport::factory()->create()->id, Sport::factory()->create(['is_active' => false])->id]);

        $response = $this->getJson('/api/v1/facilities')->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $facility->id)
            ->assertJsonCount(1, 'data.1.units')
            ->assertJsonCount(1, 'data.1.units.0.sports')
            ->assertJsonCount(1, 'data.1.spaces');
    }

    public function test_facility_show_hides_inactive_facility(): void
    {
        $active = Facility::factory()->create();
        $inactive = Facility::factory()->create(['is_active' => false]);

        $this->getJson("/api/v1/facilities/{$active->id}")->assertOk()->assertJsonPath('data.id', $active->id);
        $this->getJson("/api/v1/facilities/{$inactive->id}")->assertNotFound();
        $this->getJson('/api/v1/facilities/999999')->assertNotFound();
    }

    public function test_sports_list_only_active_ordered_by_id(): void
    {
        $a = Sport::factory()->create();
        Sport::factory()->create(['is_active' => false]);
        $b = Sport::factory()->create();

        $this->getJson('/api/v1/sports')
            ->assertOk()
            ->assertJsonPath('data.0.id', $a->id)
            ->assertJsonPath('data.1.id', $b->id)
            ->assertJsonCount(2, 'data');
    }

    public function test_coaches_filter_by_sport_and_hide_inactive(): void
    {
        $sport = Sport::factory()->create();
        $coach = Coach::factory()->create(['sport_id' => $sport->id, 'sort_order' => 2]);
        $other = Coach::factory()->create(['sport_id' => $sport->id, 'sort_order' => 1]);
        Coach::factory()->create(['sport_id' => $sport->id, 'is_active' => false]);
        Coach::factory()->create(['sport_id' => Sport::factory()]);

        $this->getJson('/api/v1/coaches?sport_id='.$sport->id)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $other->id)
            ->assertJsonPath('data.0.sport.id', $sport->id);

        $this->getJson('/api/v1/coaches')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson("/api/v1/coaches/{$coach->id}")->assertOk()->assertJsonPath('data.full_name_ar', $coach->full_name_ar);
        $this->getJson('/api/v1/coaches/'.Coach::factory()->create(['is_active' => false])->id)->assertNotFound();
    }

    public function test_academies_include_active_groups_and_current_schedules_on_show(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 10)->setTime(12, 0));
        $academy = Academy::factory()->create();
        Academy::factory()->inactive()->create();
        $coach = Coach::factory()->create();
        $group = AcademyGroup::factory()->create(['academy_id' => $academy->id, 'coach_id' => $coach->id]);
        AcademyGroup::factory()->inactive()->create(['academy_id' => $academy->id]);
        $current = GroupSchedule::factory()->create(['group_id' => $group->id, 'weekday' => 1, 'valid_to' => null]);
        GroupSchedule::factory()->create(['group_id' => $group->id, 'valid_to' => '2026-06-01']);
        GroupSchedule::factory()->create(['group_id' => $group->id, 'valid_to' => '2026-06-10']);

        $this->getJson('/api/v1/academies')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.groups')
            ->assertJsonPath('data.0.groups.0.coach.id', $coach->id);

        $response = $this->getJson("/api/v1/academies/{$academy->id}")->assertOk();
        $response->assertJsonCount(1, 'data.groups')->assertJsonCount(2, 'data.groups.0.schedules');
        $this->assertContains($current->id, collect($response->json('data.groups.0.schedules'))->pluck('id')->all());
        $response->assertJsonPath('data.groups.0.schedules.0.start_time', '16:00')
            ->assertJsonStructure(['data' => ['groups' => [['schedules' => [['unit' => ['id'], 'valid_from', 'valid_to']]]]]]);

        $this->getJson('/api/v1/academies/'.Academy::factory()->inactive()->create()->id)->assertNotFound();
    }

    public function test_academies_filter_by_sport(): void
    {
        $sport = Sport::factory()->create();
        Academy::factory()->create(['sport_id' => $sport->id]);
        Academy::factory()->create();

        $this->getJson('/api/v1/academies?sport_id='.$sport->id)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_news_hides_unpublished_and_future_and_paginates(): void
    {
        $visible = News::factory()->create(['is_published' => true, 'published_at' => now()->subDay()]);
        $newest = News::factory()->create(['is_published' => true, 'published_at' => now()->subHour()]);
        $noDate = News::factory()->create(['is_published' => true, 'published_at' => null]);
        $draft = News::factory()->create(['is_published' => false, 'published_at' => now()->subDay()]);
        $future = News::factory()->create(['is_published' => true, 'published_at' => now()->addDay()]);

        $response = $this->getJson('/api/v1/news')->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$visible->id, $newest->id, $noDate->id], $ids);
        $this->assertSame($newest->id, $ids[0]);
        $response->assertJsonPath('meta.per_page', 15)->assertJsonPath('meta.total', 3);

        $this->getJson('/api/v1/news?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.last_page', 2);

        $this->getJson("/api/v1/news/{$visible->id}")->assertOk()->assertJsonPath('data.id', $visible->id);
        $this->getJson("/api/v1/news/{$draft->id}")->assertNotFound();
        $this->getJson("/api/v1/news/{$future->id}")->assertNotFound();
        $this->getJson('/api/v1/news/abc')->assertNotFound();
    }

    public function test_gallery_albums_list_cover_and_photos(): void
    {
        $album = GalleryAlbum::factory()->create(['sort_order' => 0]);
        $empty = GalleryAlbum::factory()->create(['sort_order' => 1]);
        $hidden = GalleryAlbum::factory()->create(['is_published' => false]);
        GalleryPhoto::factory()->create(['album_id' => $album->id, 'sort_order' => 2, 'image_url' => 'https://x.test/b.jpg']);
        GalleryPhoto::factory()->create(['album_id' => $album->id, 'sort_order' => 1, 'image_url' => 'https://x.test/a.jpg']);

        $this->getJson('/api/v1/gallery/albums')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.photos_count', 2)
            ->assertJsonPath('data.0.cover_url', 'https://x.test/a.jpg')
            ->assertJsonPath('data.1.id', $empty->id)
            ->assertJsonPath('data.1.cover_url', null)
            ->assertJsonPath('data.1.photos_count', 0);

        $this->getJson("/api/v1/gallery/albums/{$album->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.photos.0.image_url', 'https://x.test/a.jpg')
            ->assertJsonPath('data.cover_url', 'https://x.test/a.jpg');

        $this->getJson("/api/v1/gallery/albums/{$hidden->id}")->assertNotFound();
    }

    public function test_app_config_reflects_settings_and_hides_otp_internals(): void
    {
        Setting::create(['key' => 'booking.deposit_percent', 'value' => 45]);

        $response = $this->getJson('/api/v1/app-config')->assertOk();

        $response->assertJsonPath('data.booking.deposit_percent', 45)
            ->assertJsonPath('data.booking.slot_minutes', 60)
            ->assertJsonPath('data.display_timezone', 'Asia/Damascus')
            ->assertJsonPath('data.otp.resend_cooldown_seconds', 60)
            ->assertJsonPath('data.subscription.expiry_reminder_days', 7)
            ->assertJsonStructure(['data' => ['contact' => ['phone', 'whatsapp', 'address_ar', 'address_en'], 'server_time']])
            ->assertJsonMissingPath('data.otp.channel')
            ->assertJsonMissingPath('data.otp.ttl_minutes')
            ->assertJsonMissingPath('data.otp.max_attempts');
        $this->assertStringEndsWith('+03:00', $response->json('data.server_time'));
    }

    public function test_validation_messages_in_arabic_and_english(): void
    {
        $this->getJson('/api/v1/coaches?sport_id=abc')
            ->assertUnprocessable()
            ->assertJsonPath('errors.sport_id.0', __('catalog.validation.sport_id_invalid', [], 'ar'));
        $this->getJson('/api/v1/academies?sport_id=abc')->assertUnprocessable()->assertJsonValidationErrors('sport_id');

        foreach (['0', '51', 'x'] as $value) {
            $this->getJson('/api/v1/news?per_page='.$value)
                ->assertUnprocessable()
                ->assertJsonPath('errors.per_page.0', __('catalog.validation.per_page_invalid', [], 'ar'));
        }

        $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/news?per_page=99')
            ->assertUnprocessable()
            ->assertJsonPath('errors.per_page.0', __('catalog.validation.per_page_invalid', [], 'en'));
    }
}
