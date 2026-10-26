<?php

namespace App\Http\Requests\Api\V1\Bookings;

use App\Models\BookableUnit;
use App\Services\DisplayTimezone;
use App\Services\SettingStore;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class QuoteBookingRequest extends FormRequest
{
    /**
     * The longest booking in minutes.
     */
    public const MAX_DURATION_MINUTES = 600;

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
            'sport_id' => ['bail', 'nullable', 'integer', 'exists:sports,id'],
            'starts_at' => ['bail', 'required', 'date_format:Y-m-d H:i'],
            'duration_minutes' => ['bail', 'required', 'integer', 'min:1', 'max:'.self::MAX_DURATION_MINUTES],
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
            'unit_id.required' => __('pricing.validation.unit_id_invalid'),
            'unit_id.integer' => __('pricing.validation.unit_id_invalid'),
            'unit_id.exists' => __('pricing.validation.unit_id_invalid'),
            'sport_id.integer' => __('pricing.validation.sport_id_invalid'),
            'sport_id.exists' => __('pricing.validation.sport_id_invalid'),
            'starts_at.required' => __('pricing.validation.starts_at_invalid'),
            'starts_at.date_format' => __('pricing.validation.starts_at_invalid'),
            'duration_minutes.required' => __('pricing.validation.duration_invalid'),
            'duration_minutes.integer' => __('pricing.validation.duration_invalid'),
            'duration_minutes.min' => __('pricing.validation.duration_invalid'),
            'duration_minutes.max' => __('pricing.validation.duration_invalid'),
        ];
    }

    /**
     * Get the validation callbacks that check the unit, its sport and the slot length once the base rules passed.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $errors = $validator->errors();

            if (! $errors->has('unit_id')) {
                $unit = $this->unit();

                if (! $unit->is_active || ! $unit->facility->is_active) {
                    $errors->add('unit_id', __('pricing.validation.unit_unavailable'));
                } elseif ($this->filled('sport_id') && ! $errors->has('sport_id') && ! $unit->sports->contains('id', $this->integer('sport_id'))) {
                    $errors->add('sport_id', __('pricing.validation.sport_not_offered'));
                }
            }

            if (! $errors->has('duration_minutes')) {
                $slot = (int) app(SettingStore::class)->get('booking.slot_minutes');

                if ($this->integer('duration_minutes') < $slot || $this->integer('duration_minutes') % $slot !== 0) {
                    $errors->add('duration_minutes', __('pricing.validation.duration_not_multiple', ['minutes' => $slot]));
                }
            }
        }];
    }

    /**
     * The bookable unit asked for, with its facility and sports loaded once.
     */
    public function unit(): BookableUnit
    {
        return once(fn () => BookableUnit::query()->with(['facility', 'sports'])->findOrFail($this->integer('unit_id')));
    }

    /**
     * The booking start as a UTC moment.
     */
    public function startsAtUtc(): CarbonImmutable
    {
        return app(DisplayTimezone::class)->localToUtc($this->string('starts_at')->toString());
    }
}
