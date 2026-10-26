<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingStore;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Default application settings keyed by setting key.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = SettingStore::DEFAULTS;

    /**
     * Seed default settings without overwriting existing values.
     */
    public function run(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
