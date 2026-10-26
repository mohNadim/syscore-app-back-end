<?php

namespace App\Http\Requests\Api\V1\Admin\Hours;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOperatingHoursRequest extends FormRequest
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
            'hours' => ['bail', 'present', 'array', 'max:7'],
            'hours.*.weekday' => ['bail', 'required', 'integer', 'between:0,6', 'distinct'],
            'hours.*.open_time' => ['bail', 'required', 'date_format:H:i'],
            'hours.*.close_time' => ['bail', 'required', 'date_format:H:i', 'after:hours.*.open_time'],
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
            'hours.present' => __('admin_hours.validation.hours_invalid'),
            'hours.array' => __('admin_hours.validation.hours_invalid'),
            'hours.max' => __('admin_hours.validation.hours_invalid'),
            'hours.*.weekday.required' => __('admin_hours.validation.weekday_invalid'),
            'hours.*.weekday.integer' => __('admin_hours.validation.weekday_invalid'),
            'hours.*.weekday.between' => __('admin_hours.validation.weekday_invalid'),
            'hours.*.weekday.distinct' => __('admin_hours.validation.weekday_duplicate'),
            'hours.*.open_time.required' => __('admin_hours.validation.time_invalid'),
            'hours.*.open_time.date_format' => __('admin_hours.validation.time_invalid'),
            'hours.*.close_time.required' => __('admin_hours.validation.time_invalid'),
            'hours.*.close_time.date_format' => __('admin_hours.validation.time_invalid'),
            'hours.*.close_time.after' => __('admin_hours.validation.close_after_open'),
        ];
    }
}
