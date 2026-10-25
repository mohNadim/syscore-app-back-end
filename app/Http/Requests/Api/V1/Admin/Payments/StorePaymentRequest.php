<?php

namespace App\Http\Requests\Api\V1\Admin\Payments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
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
            'booking_id' => ['bail', 'required_without:subscription_id', 'prohibits:subscription_id', 'integer', 'min:1'],
            'subscription_id' => ['bail', 'required_without:booking_id', 'prohibits:booking_id', 'integer', 'min:1'],
            'amount' => ['bail', 'nullable', 'integer', 'min:1'],
            'payer_name' => ['bail', 'nullable', 'string', 'max:150'],
            'payer_phone' => ['bail', 'nullable', 'string', 'max:20'],
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
            'booking_id.required_without' => __('payments.validation.target_required'),
            'subscription_id.required_without' => __('payments.validation.target_required'),
            'booking_id.prohibits' => __('payments.validation.target_exclusive'),
            'subscription_id.prohibits' => __('payments.validation.target_exclusive'),
            'booking_id.integer' => __('payments.validation.booking_id_invalid'),
            'booking_id.min' => __('payments.validation.booking_id_invalid'),
            'subscription_id.integer' => __('payments.validation.subscription_id_invalid'),
            'subscription_id.min' => __('payments.validation.subscription_id_invalid'),
            'amount.integer' => __('admin_payments.validation.amount_invalid'),
            'amount.min' => __('admin_payments.validation.amount_invalid'),
            'payer_name.string' => __('admin_payments.validation.payer_name_invalid'),
            'payer_name.max' => __('admin_payments.validation.payer_name_invalid'),
            'payer_phone.string' => __('admin_payments.validation.payer_phone_invalid'),
            'payer_phone.max' => __('admin_payments.validation.payer_phone_invalid'),
        ];
    }
}
