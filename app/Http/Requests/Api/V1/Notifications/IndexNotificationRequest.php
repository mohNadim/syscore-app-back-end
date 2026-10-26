<?php

namespace App\Http\Requests\Api\V1\Notifications;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexNotificationRequest extends FormRequest
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
            'unread' => ['bail', 'sometimes', 'boolean'],
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
            'unread.boolean' => __('notifications.validation.unread_invalid'),
            'per_page.integer' => __('notifications.validation.per_page_invalid'),
            'per_page.min' => __('notifications.validation.per_page_invalid'),
            'per_page.max' => __('notifications.validation.per_page_invalid'),
        ];
    }
}
