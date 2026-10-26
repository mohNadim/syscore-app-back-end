<?php

namespace App\Http\Requests\Api\V1\BookingSeries;

use Illuminate\Contracts\Validation\ValidationRule;

class StoreBookingSeriesRequest extends PreviewBookingSeriesRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'payment_mode' => ['bail', 'required', 'in:online_full,deposit_then_cash'],
            'skip_unavailable' => ['bail', 'sometimes', 'boolean'],
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
            ...parent::messages(),
            'payment_mode.required' => __('booking_series.validation.payment_mode_invalid'),
            'payment_mode.in' => __('booking_series.validation.payment_mode_invalid'),
            'skip_unavailable.boolean' => __('booking_series.validation.skip_unavailable_invalid'),
        ];
    }
}
