<?php

namespace App\Http\Requests\Api\V1\Admin\Payments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexPaymentRequest extends FormRequest
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
            'status' => ['bail', 'sometimes', 'in:pending,succeeded,failed,refunded,partially_refunded'],
            'method' => ['bail', 'sometimes', 'in:sham_cash,cash'],
            'purpose' => ['bail', 'sometimes', 'in:booking_deposit,booking_full,booking_balance,subscription'],
            'booking_id' => ['bail', 'sometimes', 'integer'],
            'subscription_id' => ['bail', 'sometimes', 'integer'],
            'user_id' => ['bail', 'sometimes', 'integer'],
            'from' => ['bail', 'sometimes', 'date_format:Y-m-d'],
            'to' => ['bail', 'sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'q' => ['bail', 'sometimes', 'string', 'max:50'],
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
            'status.in' => __('admin_payments.validation.status_invalid'),
            'method.in' => __('admin_payments.validation.method_invalid'),
            'purpose.in' => __('admin_payments.validation.purpose_invalid'),
            'booking_id.integer' => __('payments.validation.booking_id_invalid'),
            'subscription_id.integer' => __('payments.validation.subscription_id_invalid'),
            'user_id.integer' => __('admin_payments.validation.user_id_invalid'),
            'from.date_format' => __('admin_payments.validation.date_invalid'),
            'to.date_format' => __('admin_payments.validation.date_invalid'),
            'to.after_or_equal' => __('admin_payments.validation.to_after_from'),
            'q.string' => __('admin_payments.validation.q_invalid'),
            'q.max' => __('admin_payments.validation.q_invalid'),
            'per_page.integer' => __('payments.validation.per_page_invalid'),
            'per_page.min' => __('payments.validation.per_page_invalid'),
            'per_page.max' => __('payments.validation.per_page_invalid'),
        ];
    }
}
