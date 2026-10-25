<?php

namespace App\Http\Requests\Api\V1\Bookings;

use App\Models\BookableUnit;
use App\Services\Bookings\BookingService;
use App\Services\DisplayTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
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
            'sport_id' => ['bail', 'required', 'integer', 'exists:sports,id'],
            'starts_at' => ['bail', 'required', 'date_format:Y-m-d H:i'],
            'duration_minutes' => ['bail', 'required', 'integer', 'min:1', 'max:'.BookingService::MAX_DURATION_MINUTES],
            'payment_mode' => ['bail', 'required', 'in:online_full,deposit_then_cash'],
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
            'unit_id.required' => __('bookings.validation.unit_id_invalid'),
            'unit_id.integer' => __('bookings.validation.unit_id_invalid'),
            'unit_id.exists' => __('bookings.validation.unit_id_invalid'),
            'sport_id.required' => __('bookings.validation.sport_id_invalid'),
            'sport_id.integer' => __('bookings.validation.sport_id_invalid'),
            'sport_id.exists' => __('bookings.validation.sport_id_invalid'),
            'starts_at.required' => __('bookings.validation.starts_at_invalid'),
            'starts_at.date_format' => __('bookings.validation.starts_at_invalid'),
            'duration_minutes.required' => __('bookings.validation.duration_invalid'),
            'duration_minutes.integer' => __('bookings.validation.duration_invalid'),
            'duration_minutes.min' => __('bookings.validation.duration_invalid'),
            'duration_minutes.max' => __('bookings.validation.duration_invalid'),
            'payment_mode.required' => __('bookings.validation.payment_mode_invalid'),
            'payment_mode.in' => __('bookings.validation.payment_mode_invalid'),
        ];
    }

    /**
     * The bookable unit asked for, with its facility, spaces and sports loaded once.
     */
    public function unit(): BookableUnit
    {
        return once(fn () => BookableUnit::query()->with(['facility', 'spaces', 'sports'])->findOrFail($this->integer('unit_id')));
    }

    /**
     * The booking start as a UTC moment.
     */
    public function startsAtUtc(): CarbonImmutable
    {
        return app(DisplayTimezone::class)->localToUtc($this->string('starts_at')->toString());
    }
}
