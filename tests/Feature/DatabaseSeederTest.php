<?php

namespace Tests\Feature;

use App\Enums\PermissionCode;
use App\Models\BookableUnit;
use App\Models\Facility;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Space;
use App\Models\Staff;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_one_permission_row_per_code(): void
    {
        $this->assertSame(count(PermissionCode::cases()), Permission::count());
        $this->assertEqualsCanonicalizing(
            array_map(fn (PermissionCode $c): string => $c->value, PermissionCode::cases()),
            Permission::pluck('code')->all(),
        );
    }

    public function test_roles_have_expected_permissions(): void
    {
        $this->assertSame(2, Role::count());

        $manager = Role::where('name_en', 'Manager')->firstOrFail();
        $this->assertTrue($manager->is_system);
        $this->assertSame(Permission::count(), $manager->permissions()->count());

        $frontDesk = Role::where('name_en', 'Front desk')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            array_map(fn (PermissionCode $c): string => $c->value, PermissionCode::frontDesk()),
            $frontDesk->permissions()->pluck('code')->all(),
        );
    }

    public function test_facilities_spaces_and_units_are_seeded(): void
    {
        $this->assertSame(2, Facility::count());
        $this->assertSame(3, Space::count());
        $this->assertSame(4, BookableUnit::count());

        $expected = [
            'PITCH_FULL' => ['A', 'B'],
            'PITCH_A' => ['A'],
            'PITCH_B' => ['B'],
            'HALL' => ['HALL'],
        ];

        foreach ($expected as $code => $spaceCodes) {
            $actual = BookableUnit::where('code', $code)->firstOrFail()->spaces()->pluck('code')->all();
            $this->assertEqualsCanonicalizing($spaceCodes, $actual, $code);
        }
    }

    public function test_default_settings_are_present(): void
    {
        $this->assertSame(count(SettingSeeder::DEFAULTS), Setting::count());

        foreach (SettingSeeder::DEFAULTS as $key => $value) {
            $this->assertSame($value, Setting::findOrFail($key)->value, $key);
        }
    }

    public function test_setting_seeder_does_not_overwrite_existing_values(): void
    {
        Setting::findOrFail('booking.slot_minutes')->update(['value' => 90]);

        $this->seed(SettingSeeder::class);

        $this->assertSame(90, Setting::findOrFail('booking.slot_minutes')->value);
    }

    public function test_admin_staff_is_seeded_once_with_manager_role(): void
    {
        $this->assertSame(1, Staff::where('username', 'admin')->count());

        $admin = Staff::where('username', 'admin')->firstOrFail();
        $this->assertTrue(Hash::check('password', $admin->password_hash));
        $this->assertTrue($admin->is_active);
        $this->assertSame('Manager', $admin->role->name_en);
    }
}
