<?php

namespace App\Http\Requests\Api\V1\BookingSeries;

use App\Models\BookableUnit;
use App\Services\Bookings\BookingService;
use App\Services\DisplayTimezone;
use App\Services\SettingStore;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreviewBookingSeriesRequest extends FormRequest
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
            'start_time' => ['bail', 'required', 'date_format:H:i'],
            'duration_minutes' => ['bail', 'required', 'integer', 'min:1', 'max:'.BookingService::MAX_DURATION_MINUTES],
            'first_date' => ['bail', 'required', 'date_format:Y-m-d'],
            'last_date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:first_date'],
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
            'unit_id.required' => __('booking_series.validation.unit_id_invalid'),
            'unit_id.integer' => __('booking_series.validation.unit_id_invalid'),
            'unit_id.exists' => __('booking_series.validation.unit_id_invalid'),
            'sport_id.required' => __('booking_series.validation.sport_id_invalid'),
            'sport_id.integer' => __('booking_series.validation.sport_id_invalid'),
            'sport_id.exists' => __('booking_series.validation.sport_id_invalid'),
            'start_time.required' => __('booking_series.validation.start_time_invalid'),
            'start_time.date_format' => __('booking_series.validation.start_time_invalid'),
            'duration_minutes.required' => __('booking_series.validation.duration_invalid'),
            'duration_minutes.integer' => __('booking_series.validation.duration_invalid'),
            'duration_minutes.min' => __('booking_series.validation.duration_invalid'),
            'duration_minutes.max' => __('booking_series.validation.duration_invalid'),
            'first_date.required' => __('booking_series.validation.date_invalid'),
            'first_date.date_format' => __('booking_series.validation.date_invalid'),
            'last_date.required' => __('booking_series.validation.date_invalid'),
            'last_date.date_format' => __('booking_series.validation.date_invalid'),
            'last_date.after_or_equal' => __('booking_series.validation.last_before_first'),
        ];
    }

    /**
     * Get the validation callbacks that check the dates against today and the booking window.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $errors = $validator->errors();
            $today = CarbonImmutable::now(app(DisplayTimezone::class)->name())->startOfDay();

            if (! $errors->has('first_date') && $this->localDate('first_date')->lt($today)) {
                $errors->add('first_date', __('booking_series.validation.first_date_past'));
            }

            if (! $errors->has('last_date')) {
                $limit = $today->addDays((int) app(SettingStore::class)->get('booking.advance_window_days'));

                if ($this->localDate('last_date')->gt($limit)) {
                    $errors->add('last_date', __('booking_series.validation.outside_booking_window'));
                }
            }
        }];
    }

    /**
     * The bookable unit asked for, with its facility, spaces and sports loaded once.
     */
    public function unit(): BookableUnit
    {
        return once(fn () => BookableUnit::query()->with(['facility', 'spaces', 'sports'])->findOrFail($this->integer('unit_id')));
    }

    /**
     * The fields shared by the preview and the creation, ready for the series service.
     *
     * @return array{unit: BookableUnit, sport_id: int, start_time: string, duration_minutes: int, first_date: string, last_date: string}
     */
    public function seriesData(): array
    {
        return [
            'unit' => $this->unit(),
            'sport_id' => $this->integer('sport_id'),
            'start_time' => $this->string('start_time')->toString(),
            'duration_minutes' => $this->integer('duration_minutes'),
            'first_date' => $this->string('first_date')->toString(),
            'last_date' => $this->string('last_date')->toString(),
        ];
    }

    /**
     * Read a date field as a local calendar day.
     */
    protected function localDate(string $field): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->string($field)->toString(), app(DisplayTimezone::class)->name())->startOfDay();
    }
}
