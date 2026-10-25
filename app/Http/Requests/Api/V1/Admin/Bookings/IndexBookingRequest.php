<?php

namespace App\Http\Requests\Api\V1\Admin\Bookings;

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
            'date' => ['bail', 'sometimes', 'date_format:Y-m-d'],
            'from' => ['bail', 'sometimes', 'date_format:Y-m-d'],
            'to' => ['bail', 'sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['bail', 'sometimes', 'in:pending_deposit,confirmed,completed,cancelled,expired,no_show'],
            'unit_id' => ['bail', 'sometimes', 'integer'],
            'facility_id' => ['bail', 'sometimes', 'integer'],
            'user_id' => ['bail', 'sometimes', 'integer'],
            'phone' => ['bail', 'sometimes', 'string', 'max:20'],
            'booking_number' => ['bail', 'sometimes', 'string', 'max:20'],
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
            'date.date_format' => __('admin_bookings.validation.date_invalid'),
            'from.date_format' => __('admin_bookings.validation.date_invalid'),
            'to.date_format' => __('admin_bookings.validation.date_invalid'),
            'to.after_or_equal' => __('admin_bookings.validation.to_after_from'),
            'status.in' => __('admin_bookings.validation.status_invalid'),
            'unit_id.integer' => __('admin_bookings.validation.unit_id_invalid'),
            'facility_id.integer' => __('admin_bookings.validation.facility_id_invalid'),
            'user_id.integer' => __('admin_bookings.validation.user_id_invalid'),
            'phone.string' => __('admin_bookings.validation.phone_filter_invalid'),
            'phone.max' => __('admin_bookings.validation.phone_filter_invalid'),
            'booking_number.string' => __('admin_bookings.validation.booking_number_invalid'),
            'booking_number.max' => __('admin_bookings.validation.booking_number_invalid'),
            'per_page.integer' => __('admin_bookings.validation.per_page_invalid'),
            'per_page.min' => __('admin_bookings.validation.per_page_invalid'),
            'per_page.max' => __('admin_bookings.validation.per_page_invalid'),
        ];
    }
}
