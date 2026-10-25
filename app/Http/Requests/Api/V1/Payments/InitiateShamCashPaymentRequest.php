<?php

namespace App\Http\Requests\Api\V1\Payments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InitiateShamCashPaymentRequest extends FormRequest
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
        ];
    }
}
