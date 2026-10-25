<?php

namespace App\Http\Requests\Api\V1\Admin\UserCategories;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddUserCategoryMembersRequest extends FormRequest
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
            'user_ids' => ['bail', 'required', 'array', 'min:1', 'max:500'],
            'user_ids.*' => ['bail', 'integer', 'distinct', 'exists:users,id'],
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
            'user_ids.required' => __('admin_user_categories.validation.user_ids_invalid'),
            'user_ids.array' => __('admin_user_categories.validation.user_ids_invalid'),
            'user_ids.min' => __('admin_user_categories.validation.user_ids_invalid'),
            'user_ids.max' => __('admin_user_categories.validation.user_ids_invalid'),
            'user_ids.*.integer' => __('admin_user_categories.validation.user_id_invalid'),
            'user_ids.*.distinct' => __('admin_user_categories.validation.user_id_duplicate'),
            'user_ids.*.exists' => __('admin_user_categories.validation.user_id_invalid'),
        ];
    }
}
