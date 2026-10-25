<?php

namespace App\Http\Requests\Api\V1\Admin\Enrollments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexSubscriptionRequest extends FormRequest
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
            'status' => ['bail', 'sometimes', 'in:pending_payment,active,expired,cancelled'],
            'group_id' => ['bail', 'sometimes', 'integer'],
            'expiring_within_days' => ['bail', 'sometimes', 'integer', 'min:0', 'max:365'],
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
            'status.in' => __('admin_enrollments.validation.subscription_status_invalid'),
            'group_id.integer' => __('admin_enrollments.validation.group_id_invalid'),
            'expiring_within_days.integer' => __('admin_enrollments.validation.expiring_within_days_invalid'),
            'expiring_within_days.min' => __('admin_enrollments.validation.expiring_within_days_invalid'),
            'expiring_within_days.max' => __('admin_enrollments.validation.expiring_within_days_invalid'),
            'per_page.integer' => __('admin_enrollments.validation.per_page_invalid'),
            'per_page.min' => __('admin_enrollments.validation.per_page_invalid'),
            'per_page.max' => __('admin_enrollments.validation.per_page_invalid'),
        ];
    }
}
