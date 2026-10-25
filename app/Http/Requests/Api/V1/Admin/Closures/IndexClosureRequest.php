<?php

namespace App\Http\Requests\Api\V1\Admin\Closures;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexClosureRequest extends FormRequest
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
            'facility_id' => ['bail', 'sometimes', 'integer'],
            'space_id' => ['bail', 'sometimes', 'integer'],
            'from' => ['bail', 'sometimes', 'date_format:Y-m-d'],
            'to' => ['bail', 'sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'upcoming' => ['bail', 'sometimes', 'boolean'],
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
            'facility_id.integer' => __('admin_hours.validation.facility_id_invalid'),
            'space_id.integer' => __('admin_hours.validation.space_id_invalid'),
            'from.date_format' => __('admin_hours.validation.date_invalid'),
            'to.date_format' => __('admin_hours.validation.date_invalid'),
            'to.after_or_equal' => __('admin_hours.validation.to_after_from'),
            'upcoming.boolean' => __('admin_hours.validation.upcoming_invalid'),
            'per_page.integer' => __('admin_hours.validation.per_page_invalid'),
            'per_page.min' => __('admin_hours.validation.per_page_invalid'),
            'per_page.max' => __('admin_hours.validation.per_page_invalid'),
        ];
    }
}
