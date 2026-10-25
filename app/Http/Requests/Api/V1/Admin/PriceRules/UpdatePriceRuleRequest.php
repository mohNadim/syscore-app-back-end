<?php

namespace App\Http\Requests\Api\V1\Admin\PriceRules;

use App\Models\PriceRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class UpdatePriceRuleRequest extends StorePriceRuleRequest
{
    /**
     * Get the validation rules that apply to the request; every field is optional on update.
     * The order of the times and dates and the overlap are checked against the merged values in `after()`.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['bail', 'sometimes', 'required', 'integer', 'exists:bookable_units,id'],
            'weekday' => ['bail', 'sometimes', 'nullable', 'integer', 'between:0,6'],
            'start_time' => ['bail', 'sometimes', 'required', 'date_format:H:i', $this->halfHourStep()],
            'end_time' => ['bail', 'sometimes', 'required', 'date_format:H:i', $this->halfHourStep()],
            'is_peak' => ['bail', 'sometimes', 'boolean'],
            'price_per_hour' => ['bail', 'sometimes', 'required', 'integer', 'min:0', 'max:100000000'],
            'valid_from' => ['bail', 'sometimes', 'required', 'date_format:Y-m-d'],
            'valid_to' => ['bail', 'sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Get the validation callbacks that run once the base rules have passed.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var PriceRule $rule */
            $rule = $this->route('price_rule');

            $values = [
                'unit_id' => $this->has('unit_id') ? (int) $this->input('unit_id') : $rule->unit_id,
                'weekday' => $this->has('weekday') ? ($this->input('weekday') === null ? null : (int) $this->input('weekday')) : $rule->weekday,
                'start_time' => $this->has('start_time') ? $this->input('start_time') : substr((string) $rule->start_time, 0, 5),
                'end_time' => $this->has('end_time') ? $this->input('end_time') : substr((string) $rule->end_time, 0, 5),
                'valid_from' => $this->has('valid_from') ? $this->input('valid_from') : $rule->valid_from->format('Y-m-d'),
                'valid_to' => $this->has('valid_to') ? $this->input('valid_to') : $rule->valid_to?->format('Y-m-d'),
            ];

            if ($values['end_time'] <= $values['start_time']) {
                $validator->errors()->add('end_time', __('admin_price_rules.validation.end_after_start'));
            }

            if ($values['valid_to'] !== null && $values['valid_to'] < $values['valid_from']) {
                $validator->errors()->add('valid_to', __('admin_price_rules.validation.valid_to_after_from'));
            }

            if ($validator->errors()->isEmpty()) {
                $this->rejectOverlap($validator, $values, $rule->id);
            }
        }];
    }

    /**
     * Get only the validated price rule attributes that were sent.
     *
     * @return array<string, mixed>
     */
    public function priceRuleAttributes(): array
    {
        return $this->withFullTimes($this->validated());
    }
}
