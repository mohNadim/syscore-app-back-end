<?php

namespace Tests\Feature;

use App\Models\BookableUnit;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_explicit_staff_actor(): void
    {
        $staff = Staff::factory()->create();
        $unit = BookableUnit::factory()->create();

        $log = app(AuditLogger::class)->log('unit.updated', $unit, ['a' => 1], ['a' => 2], $staff);

        $this->assertSame('staff', $log->actor_type);
        $this->assertSame($staff->id, $log->actor_staff_id);
        $this->assertNull($log->actor_user_id);
        $this->assertSame('bookable_unit', $log->entity_type);
        $this->assertSame($unit->id, $log->entity_id);
        $this->assertSame(['a' => 1], $log->before);
        $this->assertSame(['a' => 2], $log->after);
    }

    public function test_logs_explicit_user_actor(): void
    {
        $user = User::factory()->create();
        $unit = BookableUnit::factory()->create();

        $log = app(AuditLogger::class)->log('unit.viewed', $unit, actor: $user);

        $this->assertSame('user', $log->actor_type);
        $this->assertSame($user->id, $log->actor_user_id);
        $this->assertNull($log->actor_staff_id);
    }

    public function test_defaults_to_authenticated_actor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $log = app(AuditLogger::class)->log('unit.viewed', BookableUnit::factory()->create());

        $this->assertSame('user', $log->actor_type);
        $this->assertSame($user->id, $log->actor_user_id);
    }

    public function test_logs_system_actor_when_unauthenticated(): void
    {
        $log = app(AuditLogger::class)->log('unit.created', BookableUnit::factory()->create());

        $this->assertSame('system', $log->actor_type);
        $this->assertNull($log->actor_user_id);
        $this->assertNull($log->actor_staff_id);
    }

    public function test_log_change_captures_only_changed_fields_and_excludes_hidden(): void
    {
        $staff = Staff::factory()->create(['full_name' => 'Old Name']);

        $staff->update(['full_name' => 'New Name', 'password_hash' => 'new-secret']);

        $log = app(AuditLogger::class)->logChange('staff.updated', $staff);

        $this->assertSame('staff', $log->entity_type);
        $this->assertSame(['full_name' => 'Old Name'], $log->before);
        $this->assertSame(['full_name' => 'New Name'], $log->after);
        $this->assertArrayNotHasKey('password_hash', $log->before);
        $this->assertArrayNotHasKey('password_hash', $log->after);
    }
}
