<?php

namespace App\Http\Requests\Api\V1\Bookings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexBookingRequest extends FormRequest
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
            'status' => ['bail', 'sometimes', 'in:pending_deposit,confirmed,completed,cancelled,expired,no_show'],
            'scope' => ['bail', 'sometimes', 'in:upcoming,past'],
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
            'status.in' => __('bookings.validation.status_invalid'),
            'scope.in' => __('bookings.validation.scope_invalid'),
            'per_page.integer' => __('bookings.validation.per_page_invalid'),
            'per_page.min' => __('bookings.validation.per_page_invalid'),
            'per_page.max' => __('bookings.validation.per_page_invalid'),
        ];
    }
}
