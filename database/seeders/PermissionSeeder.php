<?php

namespace Database\Seeders;

use App\Enums\PermissionCode;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seed one permission row per permission code.
     */
    public function run(): void
    {
        foreach (PermissionCode::cases() as $code) {
            Permission::updateOrCreate(
                ['code' => $code->value],
                ['description' => $this->describe($code)],
            );
        }
    }

    /**
     * Build a short English description for a permission code.
     */
    private function describe(PermissionCode $code): string
    {
        [$resource, $ability] = explode('.', $code->value);

        return ucfirst($ability).' '.str_replace('_', ' ', $resource);
    }
}
