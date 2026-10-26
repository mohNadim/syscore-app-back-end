<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'username' => ['bail', 'required', 'string', 'max:100'],
            'password' => ['bail', 'required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
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
            'username.required' => __('admin_auth.validation.username_required'),
            'username.string' => __('admin_auth.validation.username_invalid'),
            'username.max' => __('admin_auth.validation.username_invalid'),
            'password.required' => __('admin_auth.validation.password_required'),
            'password.string' => __('admin_auth.validation.password_invalid'),
            'password.max' => __('admin_auth.validation.password_invalid'),
            'device_name.string' => __('admin_auth.validation.device_name_invalid'),
            'device_name.max' => __('admin_auth.validation.device_name_invalid'),
        ];
    }
}
