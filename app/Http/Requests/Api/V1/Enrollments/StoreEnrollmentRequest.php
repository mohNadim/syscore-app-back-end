<?php

namespace App\Http\Requests\Api\V1\Enrollments;

use App\Models\Subscription;
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
            'child_id' => ['bail', 'required', 'integer', 'exists:children,id'],
            'group_id' => ['bail', 'required', 'integer', 'exists:academy_groups,id'],
            'payment_method' => ['bail', 'nullable', 'string', Rule::in(Subscription::PAYMENT_METHODS)],
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
            'child_id.required' => __('enrollments.validation.child_id_invalid'),
            'child_id.integer' => __('enrollments.validation.child_id_invalid'),
            'child_id.exists' => __('enrollments.validation.child_id_invalid'),
            'group_id.required' => __('enrollments.validation.group_id_invalid'),
            'group_id.integer' => __('enrollments.validation.group_id_invalid'),
            'group_id.exists' => __('enrollments.validation.group_id_invalid'),
            'payment_method.string' => __('enrollments.validation.payment_method_invalid'),
            'payment_method.in' => __('enrollments.validation.payment_method_invalid'),
        ];
    }
}
