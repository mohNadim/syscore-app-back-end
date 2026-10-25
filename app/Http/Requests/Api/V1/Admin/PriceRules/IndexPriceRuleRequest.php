<?php

namespace App\Http\Requests\Api\V1\Admin\PriceRules;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexPriceRuleRequest extends FormRequest
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
            'unit_id' => ['bail', 'sometimes', 'integer'],
            'weekday' => ['bail', 'sometimes', 'integer', 'between:0,6'],
            'is_peak' => ['bail', 'sometimes', 'boolean'],
            'active_on' => ['bail', 'sometimes', 'date_format:Y-m-d'],
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
            'unit_id.integer' => __('admin_price_rules.validation.unit_id_invalid'),
            'weekday.integer' => __('admin_price_rules.validation.weekday_invalid'),
            'weekday.between' => __('admin_price_rules.validation.weekday_invalid'),
            'is_peak.boolean' => __('admin_price_rules.validation.is_peak_invalid'),
            'active_on.date_format' => __('admin_price_rules.validation.date_invalid'),
        ];
    }
}
