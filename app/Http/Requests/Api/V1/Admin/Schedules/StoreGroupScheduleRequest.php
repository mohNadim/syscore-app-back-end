<?php

namespace App\Http\Requests\Api\V1\Admin\Schedules;

use App\Models\AcademyGroup;
use App\Models\BookableUnit;
use App\Models\GroupSchedule;
use App\Services\DisplayTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreGroupScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['bail', 'required', 'integer', 'exists:bookable_units,id'],
            'weekday' => ['bail', 'required', 'integer', 'between:0,6'],
            'start_time' => ['bail', 'required', 'date_format:H:i'],
            'end_time' => ['bail', 'required', 'date_format:H:i'],
            'valid_from' => ['bail', 'required', 'date_format:Y-m-d'],
            'valid_to' => ['bail', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_id.required' => __('admin_schedules.validation.unit_id_required'),
            'unit_id.integer' => __('admin_schedules.validation.unit_id_invalid'),
            'unit_id.exists' => __('admin_schedules.validation.unit_id_invalid'),
            'weekday.required' => __('admin_schedules.validation.weekday_required'),
            'weekday.integer' => __('admin_schedules.validation.weekday_invalid'),
            'weekday.between' => __('admin_schedules.validation.weekday_invalid'),
            'start_time.required' => __('admin_schedules.validation.start_time_required'),
            'start_time.date_format' => __('admin_schedules.validation.time_invalid'),
            'end_time.required' => __('admin_schedules.validation.end_time_required'),
            'end_time.date_format' => __('admin_schedules.validation.time_invalid'),
            'valid_from.required' => __('admin_schedules.validation.valid_from_required'),
            'valid_from.date_format' => __('admin_schedules.validation.date_invalid'),
            'valid_to.date_format' => __('admin_schedules.validation.date_invalid'),
        ];
    }

    /**
     * Cross-field checks on the effective values (sent value, else the stored schedule on update).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateEffectiveValues($validator);
            },
        ];
    }

    /**
     * Get the schedule being updated, or null when creating.
     */
    protected function currentSchedule(): ?GroupSchedule
    {
        return null;
    }

    /**
     * Whether valid_from must not be in the past (local date); only enforced on creation.
     */
    protected function requiresFutureValidFrom(): bool
    {
        return true;
    }

    /**
     * Validate unit, sport, time range, operating hours and the validity dates together.
     */
    protected function validateEffectiveValues(Validator $validator): void
    {
        $schedule = $this->currentSchedule();
        $group = $schedule?->group ?? $this->route('group');

        if (! $group instanceof AcademyGroup) {
            return;
        }

        $value = fn (string $key, mixed $stored): mixed => $this->has($key) ? $this->input($key) : $stored;

        $unitId = (int) $value('unit_id', $schedule?->unit_id);
        $weekday = (int) $value('weekday', $schedule?->weekday);
        $startTime = (string) $value('start_time', $schedule ? substr((string) $schedule->start_time, 0, 5) : null);
        $endTime = (string) $value('end_time', $schedule ? substr((string) $schedule->end_time, 0, 5) : null);
        $validFrom = (string) $value('valid_from', $schedule?->valid_from->toDateString());
        $validTo = $value('valid_to', $schedule?->valid_to?->toDateString());

        if ($validTo !== null && $validTo < $validFrom) {
            $validator->errors()->add('valid_to', __('admin_schedules.validation.valid_to_before_from'));
        }

        if ($this->requiresFutureValidFrom()) {
            $today = CarbonImmutable::now(app(DisplayTimezone::class)->name())->toDateString();

            if ($validFrom < $today) {
                $validator->errors()->add('valid_from', __('admin_schedules.validation.valid_from_past'));
            }
        }

        if ($endTime <= $startTime) {
            $validator->errors()->add('end_time', __('admin_schedules.validation.end_before_start'));
        }

        $unit = BookableUnit::query()->with('facility.operatingHours')->find($unitId);

        if ($unit === null) {
            return;
        }

        if (! $unit->is_active || ! $unit->facility->is_active) {
            $validator->errors()->add('unit_id', __('admin_schedules.validation.unit_inactive'));
        } elseif (! $unit->sports()->whereKey($group->academy->sport_id)->exists()) {
            $validator->errors()->add('unit_id', __('admin_schedules.validation.unit_sport_mismatch'));
        }

        if ($validator->errors()->has('end_time')) {
            return;
        }

        $hours = $unit->facility->operatingHours->firstWhere('weekday', $weekday);

        if ($hours === null
            || $startTime < substr((string) $hours->open_time, 0, 5)
            || $endTime > substr((string) $hours->close_time, 0, 5)) {
            $validator->errors()->add('start_time', __('admin_schedules.validation.outside_operating_hours'));
        }
    }
}
