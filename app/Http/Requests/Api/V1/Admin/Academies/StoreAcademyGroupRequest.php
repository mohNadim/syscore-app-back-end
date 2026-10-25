<?php

namespace App\Http\Requests\Api\V1\Admin\Academies;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAcademyGroupRequest extends FormRequest
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
            'coach_id' => ['bail', 'nullable', 'integer', 'exists:coaches,id'],
            'name_ar' => ['bail', 'required', 'string', 'max:150'],
            'name_en' => ['bail', 'nullable', 'string', 'max:150'],
            'age_min' => ['bail', 'required', 'integer', 'between:0,30'],
            'age_max' => ['bail', 'required', 'integer', 'between:0,30', 'gte:age_min'],
            'gender' => ['bail', 'required', 'in:male,female,mixed'],
            'capacity' => ['bail', 'required', 'integer', 'between:1,500'],
            'level' => ['bail', 'nullable', 'string', 'max:50'],
            'monthly_price' => ['bail', 'required', 'integer', 'between:0,100000000'],
            'subscription_days' => ['bail', 'sometimes', 'integer', 'between:1,365'],
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
            'coach_id.integer' => __('admin_academies.validation.coach_id_invalid'),
            'coach_id.exists' => __('admin_academies.validation.coach_id_invalid'),
            'name_ar.required' => __('admin_academies.validation.name_ar_required'),
            'name_ar.string' => __('admin_academies.validation.name_ar_invalid'),
            'name_ar.max' => __('admin_academies.validation.name_ar_invalid'),
            'name_en.string' => __('admin_academies.validation.name_en_invalid'),
            'name_en.max' => __('admin_academies.validation.name_en_invalid'),
            'age_min.required' => __('admin_academies.validation.age_min_required'),
            'age_min.integer' => __('admin_academies.validation.age_min_invalid'),
            'age_min.between' => __('admin_academies.validation.age_min_invalid'),
            'age_max.required' => __('admin_academies.validation.age_max_required'),
            'age_max.integer' => __('admin_academies.validation.age_max_invalid'),
            'age_max.between' => __('admin_academies.validation.age_max_invalid'),
            'age_max.gte' => __('admin_academies.validation.age_max_below_min'),
            'gender.required' => __('admin_academies.validation.gender_required'),
            'gender.in' => __('admin_academies.validation.gender_invalid'),
            'capacity.required' => __('admin_academies.validation.capacity_required'),
            'capacity.integer' => __('admin_academies.validation.capacity_invalid'),
            'capacity.between' => __('admin_academies.validation.capacity_invalid'),
            'level.string' => __('admin_academies.validation.level_invalid'),
            'level.max' => __('admin_academies.validation.level_invalid'),
            'monthly_price.required' => __('admin_academies.validation.monthly_price_required'),
            'monthly_price.integer' => __('admin_academies.validation.monthly_price_invalid'),
            'monthly_price.between' => __('admin_academies.validation.monthly_price_invalid'),
            'subscription_days.integer' => __('admin_academies.validation.subscription_days_invalid'),
            'subscription_days.between' => __('admin_academies.validation.subscription_days_invalid'),
            'is_active.boolean' => __('admin_academies.validation.is_active_invalid'),
        ];
    }
}
