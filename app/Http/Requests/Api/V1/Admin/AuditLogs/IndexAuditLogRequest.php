<?php

namespace App\Http\Requests\Api\V1\Admin\AuditLogs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexAuditLogRequest extends FormRequest
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
            'actor_type' => ['bail', 'sometimes', 'in:user,staff,system'],
            'actor_user_id' => ['bail', 'sometimes', 'integer', 'min:1'],
            'actor_staff_id' => ['bail', 'sometimes', 'integer', 'min:1'],
            'action' => ['bail', 'sometimes', 'string', 'max:100'],
            'entity_type' => ['bail', 'sometimes', 'string', 'max:50'],
            'entity_id' => ['bail', 'sometimes', 'integer', 'min:1'],
            'date_from' => ['bail', 'sometimes', 'date_format:Y-m-d'],
            'date_to' => ['bail', 'sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
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
            'actor_type.in' => __('admin_audit_logs.validation.actor_type_invalid'),
            'actor_user_id.integer' => __('admin_audit_logs.validation.actor_user_id_invalid'),
            'actor_user_id.min' => __('admin_audit_logs.validation.actor_user_id_invalid'),
            'actor_staff_id.integer' => __('admin_audit_logs.validation.actor_staff_id_invalid'),
            'actor_staff_id.min' => __('admin_audit_logs.validation.actor_staff_id_invalid'),
            'action.string' => __('admin_audit_logs.validation.action_invalid'),
            'action.max' => __('admin_audit_logs.validation.action_invalid'),
            'entity_type.string' => __('admin_audit_logs.validation.entity_type_invalid'),
            'entity_type.max' => __('admin_audit_logs.validation.entity_type_invalid'),
            'entity_id.integer' => __('admin_audit_logs.validation.entity_id_invalid'),
            'entity_id.min' => __('admin_audit_logs.validation.entity_id_invalid'),
            'date_from.date_format' => __('admin_audit_logs.validation.date_invalid'),
            'date_to.date_format' => __('admin_audit_logs.validation.date_invalid'),
            'date_to.after_or_equal' => __('admin_audit_logs.validation.date_to_after_from'),
            'per_page.integer' => __('payments.validation.per_page_invalid'),
            'per_page.min' => __('payments.validation.per_page_invalid'),
            'per_page.max' => __('payments.validation.per_page_invalid'),
        ];
    }

    /**
     * Get the display names for the validation attributes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'actor_type' => __('admin_audit_logs.attributes.actor_type'),
            'actor_user_id' => __('admin_audit_logs.attributes.actor_user_id'),
            'actor_staff_id' => __('admin_audit_logs.attributes.actor_staff_id'),
            'action' => __('admin_audit_logs.attributes.action'),
            'entity_type' => __('admin_audit_logs.attributes.entity_type'),
            'entity_id' => __('admin_audit_logs.attributes.entity_id'),
            'date_from' => __('admin_audit_logs.attributes.date_from'),
            'date_to' => __('admin_audit_logs.attributes.date_to'),
        ];
    }
}
