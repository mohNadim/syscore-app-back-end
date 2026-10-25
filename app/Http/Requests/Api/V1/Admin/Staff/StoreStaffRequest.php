<?php

namespace App\Http\Requests\Api\V1\Admin\Staff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
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
            'full_name' => ['bail', 'required', 'string', 'min:2', 'max:150'],
            'username' => ['bail', 'required', 'string', 'min:3', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('staff', 'username')],
            'phone' => ['bail', 'nullable', 'string', 'max:20'],
            'password' => ['bail', 'required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['bail', 'required', 'integer', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
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
            'full_name.required' => __('admin_staff.validation.full_name_required'),
            'full_name.string' => __('admin_staff.validation.full_name_invalid'),
            'full_name.min' => __('admin_staff.validation.full_name_length'),
            'full_name.max' => __('admin_staff.validation.full_name_length'),
            'username.required' => __('admin_staff.validation.username_required'),
            'username.string' => __('admin_staff.validation.username_invalid'),
            'username.min' => __('admin_staff.validation.username_length'),
            'username.max' => __('admin_staff.validation.username_length'),
            'username.regex' => __('admin_staff.validation.username_format'),
            'username.unique' => __('admin_staff.validation.username_taken'),
            'phone.string' => __('admin_staff.validation.phone_invalid'),
            'phone.max' => __('admin_staff.validation.phone_invalid'),
            'password.required' => __('admin_staff.validation.password_required'),
            'password.string' => __('admin_staff.validation.password_invalid'),
            'password.min' => __('admin_staff.validation.password_min'),
            'password.confirmed' => __('admin_staff.validation.password_confirmed'),
            'role_id.required' => __('admin_staff.validation.role_id_required'),
            'role_id.integer' => __('admin_staff.validation.role_id_invalid'),
            'role_id.exists' => __('admin_staff.validation.role_id_invalid'),
            'is_active.boolean' => __('admin_staff.validation.is_active_invalid'),
        ];
    }
}
