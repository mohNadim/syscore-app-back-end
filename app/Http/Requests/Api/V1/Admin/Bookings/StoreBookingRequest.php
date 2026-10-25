<?php

namespace App\Http\Requests\Api\V1\Admin\Bookings;

use App\Models\BookableUnit;
use App\Services\Bookings\BookingService;
use App\Services\DisplayTimezone;
use App\Services\Otp\PhoneNumber;
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
     * Normalize the guest phone number to +9639… before validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('guest_phone'))) {
            $this->merge(['guest_phone' => PhoneNumber::normalize($this->input('guest_phone'))]);
        }
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
            'user_id' => ['bail', 'nullable', 'integer', 'exists:users,id'],
            'guest_name' => ['bail', 'required_without:user_id', 'nullable', 'string', 'min:2', 'max:150'],
            'guest_phone' => ['bail', 'required_without:user_id', 'nullable', 'string', 'regex:'.PhoneNumber::PATTERN],
            'payment_mode' => ['bail', 'sometimes', 'in:cash_full,deposit_then_cash,online_full'],
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
            'unit_id.required' => __('admin_bookings.validation.unit_id_invalid'),
            'unit_id.integer' => __('admin_bookings.validation.unit_id_invalid'),
            'unit_id.exists' => __('admin_bookings.validation.unit_id_invalid'),
            'sport_id.required' => __('admin_bookings.validation.sport_id_invalid'),
            'sport_id.integer' => __('admin_bookings.validation.sport_id_invalid'),
            'sport_id.exists' => __('admin_bookings.validation.sport_id_invalid'),
            'starts_at.required' => __('admin_bookings.validation.starts_at_invalid'),
            'starts_at.date_format' => __('admin_bookings.validation.starts_at_invalid'),
            'duration_minutes.required' => __('admin_bookings.validation.duration_invalid'),
            'duration_minutes.integer' => __('admin_bookings.validation.duration_invalid'),
            'duration_minutes.min' => __('admin_bookings.validation.duration_invalid'),
            'duration_minutes.max' => __('admin_bookings.validation.duration_invalid'),
            'user_id.integer' => __('admin_bookings.validation.user_id_invalid'),
            'user_id.exists' => __('admin_bookings.validation.user_id_invalid'),
            'guest_name.required_without' => __('admin_bookings.validation.guest_name_required'),
            'guest_name.string' => __('admin_bookings.validation.guest_name_invalid'),
            'guest_name.min' => __('admin_bookings.validation.guest_name_invalid'),
            'guest_name.max' => __('admin_bookings.validation.guest_name_invalid'),
            'guest_phone.required_without' => __('admin_bookings.validation.guest_phone_required'),
            'guest_phone.string' => __('admin_bookings.validation.guest_phone_invalid'),
            'guest_phone.regex' => __('admin_bookings.validation.guest_phone_invalid'),
            'payment_mode.in' => __('admin_bookings.validation.payment_mode_invalid'),
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
