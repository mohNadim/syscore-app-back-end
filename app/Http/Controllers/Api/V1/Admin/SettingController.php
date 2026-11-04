<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Settings\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\SettingStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    /**
     * Show every known setting as a nested object.
     */
    public function show(): JsonResponse
    {
        return response()->json($this->payload());
    }

    /**
     * Update the sent settings; only values that actually changed are written and audited.
     */
    public function update(UpdateSettingsRequest $request, AuditLogger $audit): JsonResponse
    {
        $current = $this->currentValues();
        $changes = [];

        foreach (array_keys(SettingStore::DEFAULTS) as $key) {
            if (! Arr::has($request->validated(), $key)) {
                continue;
            }

            $new = $this->normalize($key, Arr::get($request->validated(), $key), $current[$key]);

            if ($new !== $current[$key]) {
                $changes[$key] = $new;
            }
        }

        DB::transaction(function () use ($changes, $current, $request, $audit): void {
            foreach ($changes as $key => $new) {
                $setting = Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $new, 'updated_by_staff_id' => $request->user()->id],
                );

                $audit->log('setting.updated', $setting, ['key' => $key, 'value' => $current[$key]], ['key' => $key, 'value' => $new]);
            }
        });

        return response()->json(['message' => __('admin_settings.updated'), ...$this->payload()]);
    }

    /**
     * Cast a submitted value to its stored type; the contact object is merged into the current one.
     */
    protected function normalize(string $key, mixed $value, mixed $current): mixed
    {
        if ($key === 'app.contact') {
            $merged = [];

            foreach (array_keys(SettingStore::DEFAULTS['app.contact']) as $field) {
                $merged[$field] = array_key_exists($field, $value) ? $value[$field] : ($current[$field] ?? null);
            }

            return $merged;
        }

        if (is_bool(SettingStore::DEFAULTS[$key])) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return is_int(SettingStore::DEFAULTS[$key]) ? (int) $value : $value;
    }

    /**
     * Get the stored value or the default of every known key.
     *
     * @return array<string, mixed>
     */
    protected function currentValues(): array
    {
        $stored = Setting::query()->whereIn('key', array_keys(SettingStore::DEFAULTS))->pluck('value', 'key');
        $values = [];

        foreach (SettingStore::DEFAULTS as $key => $default) {
            $values[$key] = $stored->has($key) ? $stored[$key] : $default;
        }

        return $values;
    }

    /**
     * Build the nested data and meta response body.
     *
     * @return array{data: array<string, mixed>, meta: array{updated_at: string|null}}
     */
    protected function payload(): array
    {
        $data = [];

        foreach ($this->currentValues() as $key => $value) {
            Arr::set($data, $key, $value);
        }

        $latest = Setting::query()->whereIn('key', array_keys(SettingStore::DEFAULTS))->max('updated_at');

        return [
            'data' => $data,
            'meta' => ['updated_at' => $latest ? Carbon::parse($latest)->toIso8601String() : null],
        ];
    }
}
