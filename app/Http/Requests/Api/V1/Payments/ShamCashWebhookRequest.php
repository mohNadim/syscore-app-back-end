<?php

namespace App\Http\Requests\Api\V1\Payments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShamCashWebhookRequest extends FormRequest
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
            'reference' => ['bail', 'required', 'string', 'max:100'],
            'status' => ['bail', 'required', 'in:succeeded,failed'],
            'amount' => ['bail', 'required', 'integer', 'min:0'],
            'transaction_id' => ['bail', 'nullable', 'string', 'max:100'],
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
            'reference.required' => __('payments.validation.reference_invalid'),
            'reference.string' => __('payments.validation.reference_invalid'),
            'reference.max' => __('payments.validation.reference_invalid'),
            'status.required' => __('payments.validation.status_invalid'),
            'status.in' => __('payments.validation.status_invalid'),
            'amount.required' => __('payments.validation.amount_invalid'),
            'amount.integer' => __('payments.validation.amount_invalid'),
            'amount.min' => __('payments.validation.amount_invalid'),
            'transaction_id.string' => __('payments.validation.transaction_id_invalid'),
            'transaction_id.max' => __('payments.validation.transaction_id_invalid'),
        ];
    }
}
