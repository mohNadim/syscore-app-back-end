<?php

namespace App\Http\Requests\Api\V1\Admin\Academies;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAcademyRequest extends FormRequest
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
            'sport_id' => ['bail', 'required', 'integer', 'exists:sports,id'],
            'name_ar' => ['bail', 'required', 'string', 'max:150'],
            'name_en' => ['bail', 'nullable', 'string', 'max:150'],
            'description_ar' => ['bail', 'nullable', 'string', 'max:5000'],
            'description_en' => ['bail', 'nullable', 'string', 'max:5000'],
            'is_active' => ['bail', 'sometimes', 'boolean'],
            'cover' => ['bail', 'sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_cover' => ['bail', 'sometimes', 'boolean'],
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
            'sport_id.required' => __('admin_academies.validation.sport_id_required'),
            'sport_id.integer' => __('admin_academies.validation.sport_id_invalid'),
            'sport_id.exists' => __('admin_academies.validation.sport_id_invalid'),
            'name_ar.required' => __('admin_academies.validation.name_ar_required'),
            'name_ar.string' => __('admin_academies.validation.name_ar_invalid'),
            'name_ar.max' => __('admin_academies.validation.name_ar_invalid'),
            'name_en.string' => __('admin_academies.validation.name_en_invalid'),
            'name_en.max' => __('admin_academies.validation.name_en_invalid'),
            'description_ar.string' => __('admin_academies.validation.description_invalid'),
            'description_ar.max' => __('admin_academies.validation.description_invalid'),
            'description_en.string' => __('admin_academies.validation.description_invalid'),
            'description_en.max' => __('admin_academies.validation.description_invalid'),
            'is_active.boolean' => __('admin_academies.validation.is_active_invalid'),
            'cover.file' => __('admin_academies.validation.cover_invalid'),
            'cover.image' => __('admin_academies.validation.cover_invalid'),
            'cover.mimes' => __('admin_academies.validation.cover_invalid'),
            'cover.max' => __('admin_academies.validation.cover_invalid'),
            'remove_cover.boolean' => __('admin_academies.validation.remove_cover_invalid'),
        ];
    }
}
