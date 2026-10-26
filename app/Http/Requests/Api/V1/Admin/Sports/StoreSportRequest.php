<?php

namespace App\Http\Requests\Api\V1\Admin\Sports;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSportRequest extends FormRequest
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
            'name_ar' => ['bail', 'required', 'string', 'max:100', Rule::unique('sports', 'name_ar')],
            'name_en' => ['bail', 'nullable', 'string', 'max:100'],
            'icon_url' => ['bail', 'nullable', 'url', 'max:500'],
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
            'name_ar.required' => __('admin_sports.validation.name_ar_required'),
            'name_ar.string' => __('admin_sports.validation.name_ar_invalid'),
            'name_ar.max' => __('admin_sports.validation.name_ar_invalid'),
            'name_ar.unique' => __('admin_sports.validation.name_ar_taken'),
            'name_en.string' => __('admin_sports.validation.name_en_invalid'),
            'name_en.max' => __('admin_sports.validation.name_en_invalid'),
            'icon_url.url' => __('admin_sports.validation.icon_url_invalid'),
            'icon_url.max' => __('admin_sports.validation.icon_url_invalid'),
            'is_active.boolean' => __('admin_sports.validation.is_active_invalid'),
        ];
    }
}
