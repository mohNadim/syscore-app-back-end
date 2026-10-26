<?php

namespace App\Http\Requests\Api\V1\Devices;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DestroyDeviceRequest extends FormRequest
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
            'push_token' => ['bail', 'required', 'string', 'max:500'],
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
            'push_token.required' => __('devices.validation.push_token_required'),
            'push_token.string' => __('devices.validation.push_token_invalid'),
            'push_token.max' => __('devices.validation.push_token_invalid'),
        ];
    }
}
