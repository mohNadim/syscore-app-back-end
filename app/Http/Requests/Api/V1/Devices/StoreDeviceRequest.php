<?php

namespace App\Http\Requests\Api\V1\Devices;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceRequest extends FormRequest
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
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'push_token' => ['bail', 'required', 'string', 'max:500'],
            'app_version' => ['bail', 'nullable', 'string', 'max:20'],
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
            'platform.required' => __('devices.validation.platform_required'),
            'platform.in' => __('devices.validation.platform_invalid'),
            'push_token.required' => __('devices.validation.push_token_required'),
            'push_token.string' => __('devices.validation.push_token_invalid'),
            'push_token.max' => __('devices.validation.push_token_invalid'),
            'app_version.string' => __('devices.validation.app_version_invalid'),
            'app_version.max' => __('devices.validation.app_version_invalid'),
        ];
    }
}
