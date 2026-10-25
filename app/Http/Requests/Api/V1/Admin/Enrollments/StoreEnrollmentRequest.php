<?php

namespace App\Http\Requests\Api\V1\Admin\Enrollments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnrollmentRequest extends FormRequest
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
            'child_id' => ['bail', 'required', 'integer', Rule::exists('children', 'id')->where('is_active', true)],
            'group_id' => ['bail', 'required', 'integer', 'exists:academy_groups,id'],
            'override_capacity' => ['bail', 'sometimes', 'boolean'],
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
            'child_id.required' => __('admin_enrollments.validation.child_id_invalid'),
            'child_id.integer' => __('admin_enrollments.validation.child_id_invalid'),
            'child_id.exists' => __('admin_enrollments.validation.child_id_invalid'),
            'group_id.required' => __('admin_enrollments.validation.group_id_invalid'),
            'group_id.integer' => __('admin_enrollments.validation.group_id_invalid'),
            'group_id.exists' => __('admin_enrollments.validation.group_id_invalid'),
            'override_capacity.boolean' => __('admin_enrollments.validation.override_capacity_invalid'),
        ];
    }
}
