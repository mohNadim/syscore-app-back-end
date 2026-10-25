<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Staff;
use Illuminate\Database\Seeder;

class AdminStaffSeeder extends Seeder
{
    /**
     * Seed the default admin staff member (local and testing environments only).
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $manager = Role::query()->where('name_en', 'Manager')->firstOrFail();

        Staff::firstOrCreate(
            ['username' => 'admin'],
            [
                'full_name' => 'مدير النظام',
                'password_hash' => 'password',
                'role_id' => $manager->id,
                'is_active' => true,
            ],
        );
    }
}
