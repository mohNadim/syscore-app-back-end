<?php

namespace Tests\Feature;

use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_an_announcement_with_its_sending_staff(): void
    {
        $announcement = Announcement::factory()->create();

        $this->assertNotNull($announcement->sentByStaff);
    }
}
