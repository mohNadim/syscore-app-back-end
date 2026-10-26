<?php

namespace App\Http\Requests\Api\V1\Admin\Roles;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
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
            'name_ar' => ['bail', 'required', 'string', 'max:100', Rule::unique('roles', 'name_ar')],
            'name_en' => ['bail', 'nullable', 'string', 'max:100'],
            'permission_ids' => ['bail', 'present', 'array'],
            'permission_ids.*' => ['bail', 'integer', 'distinct', 'exists:permissions,id'],
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
            'name_ar.required' => __('admin_roles.validation.name_ar_required'),
            'name_ar.string' => __('admin_roles.validation.name_ar_invalid'),
            'name_ar.max' => __('admin_roles.validation.name_ar_invalid'),
            'name_ar.unique' => __('admin_roles.validation.name_ar_taken'),
            'name_en.string' => __('admin_roles.validation.name_en_invalid'),
            'name_en.max' => __('admin_roles.validation.name_en_invalid'),
            'permission_ids.present' => __('admin_roles.validation.permission_ids_required'),
            'permission_ids.required' => __('admin_roles.validation.permission_ids_required'),
            'permission_ids.array' => __('admin_roles.validation.permission_ids_invalid'),
            'permission_ids.*.integer' => __('admin_roles.validation.permission_id_invalid'),
            'permission_ids.*.distinct' => __('admin_roles.validation.permission_id_invalid'),
            'permission_ids.*.exists' => __('admin_roles.validation.permission_id_invalid'),
        ];
    }
}
