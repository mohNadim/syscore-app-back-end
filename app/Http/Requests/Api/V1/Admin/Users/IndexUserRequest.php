<?php

namespace App\Http\Requests\Api\V1\Admin\Users;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexUserRequest extends FormRequest
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
            'search' => ['bail', 'sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['bail', 'sometimes', 'boolean'],
            'category_id' => ['bail', 'sometimes', 'integer', 'exists:user_categories,id'],
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
            'search.string' => __('admin_users.validation.search_invalid'),
            'search.max' => __('admin_users.validation.search_invalid'),
            'is_active.boolean' => __('admin_users.validation.is_active_invalid'),
            'category_id.integer' => __('admin_users.validation.category_id_invalid'),
            'category_id.exists' => __('admin_users.validation.category_id_invalid'),
            'per_page.integer' => __('admin_users.validation.per_page_invalid'),
            'per_page.min' => __('admin_users.validation.per_page_invalid'),
            'per_page.max' => __('admin_users.validation.per_page_invalid'),
        ];
    }
}
