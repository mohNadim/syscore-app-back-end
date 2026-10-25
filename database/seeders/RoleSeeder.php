<?php

namespace Database\Seeders;

use App\Enums\PermissionCode;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the system roles and their permissions.
     */
    public function run(): void
    {
        $manager = Role::updateOrCreate(
            ['name_en' => 'Manager'],
            ['name_ar' => 'مدير', 'is_system' => true],
        );
        $manager->permissions()->sync(Permission::query()->pluck('id')->all());

        $frontDesk = Role::updateOrCreate(
            ['name_en' => 'Front desk'],
            ['name_ar' => 'موظف استقبال', 'is_system' => true],
        );
        $frontDesk->permissions()->sync(
            Permission::query()
                ->whereIn('code', array_map(fn (PermissionCode $code): string => $code->value, PermissionCode::frontDesk()))
                ->pluck('id')
                ->all()
        );
    }
}
