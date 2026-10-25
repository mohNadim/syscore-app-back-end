<?php

namespace App\Http\Requests\Api\V1\Admin\UserCategories;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserCategoryRequest extends FormRequest
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
            'name_ar' => ['bail', 'required', 'string', 'max:100'],
            'name_en' => ['bail', 'nullable', 'string', 'max:100'],
            'description' => ['bail', 'nullable', 'string', 'max:1000'],
            'is_active' => ['bail', 'sometimes', 'boolean'],
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
            'name_ar.required' => __('admin_user_categories.validation.name_ar_required'),
            'name_ar.string' => __('admin_user_categories.validation.name_ar_invalid'),
            'name_ar.max' => __('admin_user_categories.validation.name_ar_invalid'),
            'name_en.string' => __('admin_user_categories.validation.name_en_invalid'),
            'name_en.max' => __('admin_user_categories.validation.name_en_invalid'),
            'description.string' => __('admin_user_categories.validation.description_invalid'),
            'description.max' => __('admin_user_categories.validation.description_invalid'),
            'is_active.boolean' => __('admin_user_categories.validation.is_active_invalid'),
        ];
    }
}
