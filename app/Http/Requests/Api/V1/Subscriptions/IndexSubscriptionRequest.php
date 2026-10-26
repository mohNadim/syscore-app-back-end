<?php

namespace App\Http\Requests\Api\V1\Subscriptions;

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
            'child_id' => ['bail', 'sometimes', 'integer'],
            'status' => ['bail', 'sometimes', 'in:pending_payment,active,expired,cancelled'],
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
            'child_id.integer' => __('subscriptions.validation.child_id_invalid'),
            'status.in' => __('subscriptions.validation.status_invalid'),
            'per_page.integer' => __('subscriptions.validation.per_page_invalid'),
            'per_page.min' => __('subscriptions.validation.per_page_invalid'),
            'per_page.max' => __('subscriptions.validation.per_page_invalid'),
        ];
    }
}
