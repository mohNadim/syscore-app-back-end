<?php

namespace App\Http\Requests\Api\V1\ChildSchedule;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChildAttendanceRequest extends FormRequest
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
            'from' => ['bail', 'sometimes', 'date_format:Y-m-d'],
            'to' => ['bail', 'sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['bail', 'sometimes', 'integer', 'min:1', 'max:100'],
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
            'from.date_format' => __('child_schedule.validation.date_invalid'),
            'to.date_format' => __('child_schedule.validation.date_invalid'),
            'to.after_or_equal' => __('child_schedule.validation.to_after_from'),
            'per_page.integer' => __('child_schedule.validation.per_page_invalid'),
            'per_page.min' => __('child_schedule.validation.per_page_invalid'),
            'per_page.max' => __('child_schedule.validation.per_page_invalid'),
        ];
    }
}
