<?php

namespace App\Http\Requests\Api\V1\Admin\Discounts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexDiscountRequest extends FormRequest
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
            'is_active' => ['bail', 'sometimes', 'boolean'],
            'target' => ['bail', 'sometimes', 'in:booking,subscription,all'],
            'active_at' => ['bail', 'sometimes', 'date_format:Y-m-d H:i'],
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
            'is_active.boolean' => __('admin_discounts.validation.is_active_invalid'),
            'target.in' => __('admin_discounts.validation.target_invalid'),
            'active_at.date_format' => __('admin_discounts.validation.active_at_invalid'),
            'per_page.integer' => __('admin_discounts.validation.per_page_invalid'),
            'per_page.min' => __('admin_discounts.validation.per_page_invalid'),
            'per_page.max' => __('admin_discounts.validation.per_page_invalid'),
        ];
    }
}
