<?php

namespace App\Http\Requests\Api\V1\Admin\Reports;

use App\Services\DisplayTimezone;
use App\Services\Reports\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class ReportRequest extends FormRequest
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
            'date_from' => ['bail', 'required', 'date_format:Y-m-d'],
            'date_to' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'format' => ['bail', 'sometimes', 'in:json,xlsx'],
        ] + $this->filterRules();
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.required' => __('admin_reports.validation.date_required'),
            'date_from.date_format' => __('admin_reports.validation.date_invalid'),
            'date_to.required' => __('admin_reports.validation.date_required'),
            'date_to.date_format' => __('admin_reports.validation.date_invalid'),
            'date_to.after_or_equal' => __('admin_reports.validation.date_to_after_from'),
            'format.in' => __('admin_reports.validation.format_invalid'),
        ] + $this->filterMessages();
    }

    /**
     * Get the display names for the validation attributes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date_from' => __('admin_reports.attributes.date_from'),
            'date_to' => __('admin_reports.attributes.date_to'),
            'format' => __('admin_reports.attributes.format'),
        ] + $this->filterAttributes();
    }

    /**
     * Get the validation callbacks that run after the basic rules.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['date_from', 'date_to'])) {
                    return;
                }

                $days = CarbonImmutable::parse($this->string('date_from')->toString())
                    ->diffInDays(CarbonImmutable::parse($this->string('date_to')->toString())) + 1;

                if ($days > ReportPeriod::MAX_DAYS) {
                    $validator->errors()->add('date_to', __('admin_reports.validation.range_too_long', ['days' => ReportPeriod::MAX_DAYS]));
                }
            },
        ];
    }

    /**
     * Build the validated report range in the display time zone.
     */
    public function period(DisplayTimezone $timezone): ReportPeriod
    {
        return new ReportPeriod($this->string('date_from')->toString(), $this->string('date_to')->toString(), $timezone);
    }

    /**
     * Whether the report must be returned as an Excel download.
     */
    public function wantsSpreadsheet(): bool
    {
        return $this->string('format')->toString() === 'xlsx';
    }

    /**
     * Report-specific validation rules.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function filterRules(): array
    {
        return [];
    }

    /**
     * Report-specific validation messages.
     *
     * @return array<string, string>
     */
    protected function filterMessages(): array
    {
        return [];
    }

    /**
     * Report-specific attribute names.
     *
     * @return array<string, string>
     */
    protected function filterAttributes(): array
    {
        return [];
    }
}
