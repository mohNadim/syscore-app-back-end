<?php

namespace App\Services;

use App\Models\Setting;

class SettingStore
{
    /**
     * Get a setting value by key, falling back to the given default when it is not stored.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::query()->find($key)?->value ?? $default;
    }
}
