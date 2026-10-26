<?php

namespace App\Http\Requests\Api\V1\Admin\Users;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncUserCategoriesRequest extends FormRequest
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
            'category_ids' => ['present', 'array', 'max:100'],
            'category_ids.*' => ['bail', 'integer', 'distinct', 'exists:user_categories,id'],
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
            'category_ids.present' => __('admin_users.validation.category_ids_invalid'),
            'category_ids.array' => __('admin_users.validation.category_ids_invalid'),
            'category_ids.max' => __('admin_users.validation.category_ids_invalid'),
            'category_ids.*.integer' => __('admin_users.validation.category_id_invalid'),
            'category_ids.*.distinct' => __('admin_users.validation.category_id_duplicate'),
            'category_ids.*.exists' => __('admin_users.validation.category_id_invalid'),
        ];
    }
}
