<?php

namespace App\Http\Requests\Api\V1\Admin\Facilities;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFacilityRequest extends FormRequest
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
            'type' => ['bail', 'required', 'in:pitch,hall'],
            'name_ar' => ['bail', 'required', 'string', 'max:100'],
            'name_en' => ['bail', 'nullable', 'string', 'max:100'],
            'description_ar' => ['bail', 'nullable', 'string', 'max:5000'],
            'description_en' => ['bail', 'nullable', 'string', 'max:5000'],
            'sort_order' => ['bail', 'sometimes', 'integer', 'min:0', 'max:65535'],
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
            'type.required' => __('admin_facilities.validation.type_invalid'),
            'type.in' => __('admin_facilities.validation.type_invalid'),
            'name_ar.required' => __('admin_facilities.validation.name_ar_required'),
            'name_ar.string' => __('admin_facilities.validation.name_ar_invalid'),
            'name_ar.max' => __('admin_facilities.validation.name_ar_invalid'),
            'name_en.string' => __('admin_facilities.validation.name_en_invalid'),
            'name_en.max' => __('admin_facilities.validation.name_en_invalid'),
            'description_ar.string' => __('admin_facilities.validation.description_ar_invalid'),
            'description_ar.max' => __('admin_facilities.validation.description_ar_invalid'),
            'description_en.string' => __('admin_facilities.validation.description_en_invalid'),
            'description_en.max' => __('admin_facilities.validation.description_en_invalid'),
            'sort_order.integer' => __('admin_facilities.validation.sort_order_invalid'),
            'sort_order.min' => __('admin_facilities.validation.sort_order_invalid'),
            'sort_order.max' => __('admin_facilities.validation.sort_order_invalid'),
            'is_active.boolean' => __('admin_facilities.validation.is_active_invalid'),
        ];
    }
}
