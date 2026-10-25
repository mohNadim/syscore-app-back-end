<?php

namespace App\Http\Requests\Api\V1\Availability;

use App\Http\Requests\Api\V1\Bookings\QuoteBookingRequest;
use App\Models\Facility;
use App\Services\DisplayTimezone;
use App\Services\SettingStore;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ShowAvailabilityRequest extends FormRequest
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
            'facility_id' => ['bail', 'required', 'integer', 'exists:facilities,id'],
            'date' => ['bail', 'required', 'date_format:Y-m-d'],
            'sport_id' => ['bail', 'nullable', 'integer', 'exists:sports,id'],
            'duration_minutes' => ['bail', 'nullable', 'integer', 'min:1', 'max:'.QuoteBookingRequest::MAX_DURATION_MINUTES],
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
            'facility_id.required' => __('availability.validation.facility_id_invalid'),
            'facility_id.integer' => __('availability.validation.facility_id_invalid'),
            'facility_id.exists' => __('availability.validation.facility_id_invalid'),
            'date.required' => __('availability.validation.date_invalid'),
            'date.date_format' => __('availability.validation.date_invalid'),
            'sport_id.integer' => __('availability.validation.sport_id_invalid'),
            'sport_id.exists' => __('availability.validation.sport_id_invalid'),
            'duration_minutes.integer' => __('availability.validation.duration_invalid'),
            'duration_minutes.min' => __('availability.validation.duration_invalid'),
            'duration_minutes.max' => __('availability.validation.duration_invalid'),
        ];
    }

    /**
     * Get the validation callbacks that check the facility, the booking window and the duration once the base rules passed.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $errors = $validator->errors();

            if (! $errors->has('facility_id') && ! $this->facility()->is_active) {
                $errors->add('facility_id', __('availability.validation.facility_unavailable'));
            }

            if (! $errors->has('date')) {
                $today = CarbonImmutable::now(app(DisplayTimezone::class)->name())->startOfDay();
                $last = $today->addDays((int) app(SettingStore::class)->get('booking.advance_window_days'));
                $date = $this->localDate();

                if ($date->lt($today) || $date->gt($last)) {
                    $errors->add('date', __('availability.validation.outside_booking_window'));
                }
            }

            if (! $errors->has('duration_minutes')) {
                $slot = (int) app(SettingStore::class)->get('booking.slot_minutes');
                $duration = $this->durationMinutes();

                if ($duration < $slot || $duration % $slot !== 0) {
                    $errors->add('duration_minutes', __('availability.validation.duration_not_multiple', ['minutes' => $slot]));
                }
            }
        }];
    }

    /**
     * The facility asked for.
     */
    public function facility(): Facility
    {
        return once(fn () => Facility::query()->findOrFail($this->integer('facility_id')));
    }

    /**
     * The requested local calendar day at its start.
     */
    public function localDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->string('date')->toString(), app(DisplayTimezone::class)->name())->startOfDay();
    }

    /**
     * The requested booking length, defaulting to one slot.
     */
    public function durationMinutes(): int
    {
        return $this->filled('duration_minutes')
            ? $this->integer('duration_minutes')
            : (int) app(SettingStore::class)->get('booking.slot_minutes');
    }
}
