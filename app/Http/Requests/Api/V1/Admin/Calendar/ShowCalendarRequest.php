<?php

namespace App\Http\Requests\Api\V1\Admin\Calendar;

use App\Services\DisplayTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowCalendarRequest extends FormRequest
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
            'view' => ['bail', 'sometimes', 'in:day,week'],
            'facility_id' => ['bail', 'required', 'integer', 'exists:facilities,id'],
            'date' => ['bail', 'sometimes', 'date_format:Y-m-d'],
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
            'view.in' => __('admin_calendar.validation.view_invalid'),
            'facility_id.required' => __('admin_calendar.validation.facility_id_invalid'),
            'facility_id.integer' => __('admin_calendar.validation.facility_id_invalid'),
            'facility_id.exists' => __('admin_calendar.validation.facility_id_invalid'),
            'date.date_format' => __('admin_calendar.validation.date_invalid'),
        ];
    }

    /**
     * The requested view, day by default.
     */
    public function view(): string
    {
        return $this->string('view', 'day')->toString();
    }

    /**
     * The first local day of the range: the date itself, or the Sunday of its week.
     */
    public function fromDate(): CarbonImmutable
    {
        $date = $this->filled('date')
            ? CarbonImmutable::createFromFormat('Y-m-d', $this->string('date')->toString(), app(DisplayTimezone::class)->name())->startOfDay()
            : CarbonImmutable::now(app(DisplayTimezone::class)->name())->startOfDay();

        return $this->view() === 'week' ? $date->startOfWeek(CarbonImmutable::SUNDAY) : $date;
    }

    /**
     * The last local day of the range: the date itself, or the Saturday of its week.
     */
    public function toDate(): CarbonImmutable
    {
        return $this->view() === 'week' ? $this->fromDate()->addDays(6) : $this->fromDate();
    }
}
