<?php

namespace App\Http\Requests\Api\V1\Admin\Staff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexStaffRequest extends FormRequest
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
            'search' => ['bail', 'nullable', 'string', 'max:100'],
            'role_id' => ['bail', 'nullable', 'integer', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['bail', 'nullable', 'integer', 'min:1', 'max:100'],
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
            'search.string' => __('admin_staff.validation.search_invalid'),
            'search.max' => __('admin_staff.validation.search_invalid'),
            'role_id.integer' => __('admin_staff.validation.role_id_invalid'),
            'role_id.exists' => __('admin_staff.validation.role_id_invalid'),
            'is_active.boolean' => __('admin_staff.validation.is_active_invalid'),
            'per_page.integer' => __('admin_staff.validation.per_page_invalid'),
            'per_page.min' => __('admin_staff.validation.per_page_invalid'),
            'per_page.max' => __('admin_staff.validation.per_page_invalid'),
        ];
    }
}
