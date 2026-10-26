<?php

namespace App\Http\Requests\Api\V1\Admin\Coaches;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCoachRequest extends FormRequest
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
            'full_name_ar' => ['bail', 'required', 'string', 'max:150'],
            'full_name_en' => ['bail', 'nullable', 'string', 'max:150'],
            'bio_ar' => ['bail', 'nullable', 'string', 'max:5000'],
            'bio_en' => ['bail', 'nullable', 'string', 'max:5000'],
            'sport_id' => ['bail', 'nullable', 'integer', 'exists:sports,id'],
            'is_active' => ['bail', 'sometimes', 'boolean'],
            'sort_order' => ['bail', 'sometimes', 'integer', 'between:0,65535'],
            'photo' => ['bail', 'sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['bail', 'sometimes', 'boolean'],
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
            'full_name_ar.required' => __('admin_coaches.validation.full_name_ar_required'),
            'full_name_ar.string' => __('admin_coaches.validation.full_name_ar_invalid'),
            'full_name_ar.max' => __('admin_coaches.validation.full_name_ar_invalid'),
            'full_name_en.string' => __('admin_coaches.validation.full_name_en_invalid'),
            'full_name_en.max' => __('admin_coaches.validation.full_name_en_invalid'),
            'bio_ar.string' => __('admin_coaches.validation.bio_invalid'),
            'bio_ar.max' => __('admin_coaches.validation.bio_invalid'),
            'bio_en.string' => __('admin_coaches.validation.bio_invalid'),
            'bio_en.max' => __('admin_coaches.validation.bio_invalid'),
            'sport_id.integer' => __('admin_coaches.validation.sport_id_invalid'),
            'sport_id.exists' => __('admin_coaches.validation.sport_id_invalid'),
            'is_active.boolean' => __('admin_coaches.validation.is_active_invalid'),
            'sort_order.integer' => __('admin_coaches.validation.sort_order_invalid'),
            'sort_order.between' => __('admin_coaches.validation.sort_order_invalid'),
            'photo.file' => __('admin_coaches.validation.photo_invalid'),
            'photo.image' => __('admin_coaches.validation.photo_invalid'),
            'photo.mimes' => __('admin_coaches.validation.photo_invalid'),
            'photo.max' => __('admin_coaches.validation.photo_invalid'),
            'remove_photo.boolean' => __('admin_coaches.validation.remove_photo_invalid'),
        ];
    }
}
