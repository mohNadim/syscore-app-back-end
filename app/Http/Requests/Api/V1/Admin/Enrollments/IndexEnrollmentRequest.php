<?php

namespace App\Http\Requests\Api\V1\Admin\Enrollments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexEnrollmentRequest extends FormRequest
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
            'group_id' => ['bail', 'sometimes', 'integer'],
            'child_id' => ['bail', 'sometimes', 'integer'],
            'user_id' => ['bail', 'sometimes', 'integer'],
            'status' => ['bail', 'sometimes', 'in:active,ended,cancelled'],
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
            'group_id.integer' => __('admin_enrollments.validation.group_id_invalid'),
            'child_id.integer' => __('admin_enrollments.validation.child_id_invalid'),
            'user_id.integer' => __('admin_enrollments.validation.user_id_invalid'),
            'status.in' => __('admin_enrollments.validation.status_invalid'),
            'per_page.integer' => __('admin_enrollments.validation.per_page_invalid'),
            'per_page.min' => __('admin_enrollments.validation.per_page_invalid'),
            'per_page.max' => __('admin_enrollments.validation.per_page_invalid'),
        ];
    }
}
