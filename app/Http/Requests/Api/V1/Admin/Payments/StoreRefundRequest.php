<?php

namespace App\Http\Requests\Api\V1\Admin\Payments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRefundRequest extends FormRequest
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
            'amount' => ['bail', 'required', 'integer', 'min:1'],
            'reason' => ['bail', 'nullable', 'string', 'max:255'],
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
            'amount.required' => __('admin_payments.validation.refund_amount_invalid'),
            'amount.integer' => __('admin_payments.validation.refund_amount_invalid'),
            'amount.min' => __('admin_payments.validation.refund_amount_invalid'),
            'reason.string' => __('admin_payments.validation.reason_invalid'),
            'reason.max' => __('admin_payments.validation.reason_invalid'),
        ];
    }
}
