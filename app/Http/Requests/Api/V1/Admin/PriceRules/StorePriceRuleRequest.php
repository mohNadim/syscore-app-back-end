<?php

namespace App\Http\Requests\Api\V1\Admin\PriceRules;

use App\Services\Pricing\PriceRuleOverlapChecker;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePriceRuleRequest extends FormRequest
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
            'unit_id' => ['bail', 'required', 'integer', 'exists:bookable_units,id'],
            'weekday' => ['bail', 'nullable', 'integer', 'between:0,6'],
            'start_time' => ['bail', 'required', 'date_format:H:i', $this->halfHourStep()],
            'end_time' => ['bail', 'required', 'date_format:H:i', 'after:start_time', $this->halfHourStep()],
            'is_peak' => ['bail', 'sometimes', 'boolean'],
            'price_per_hour' => ['bail', 'required', 'integer', 'min:0', 'max:100000000'],
            'valid_from' => ['bail', 'required', 'date_format:Y-m-d'],
            'valid_to' => ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
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
            'unit_id.required' => __('admin_price_rules.validation.unit_id_invalid'),
            'unit_id.integer' => __('admin_price_rules.validation.unit_id_invalid'),
            'unit_id.exists' => __('admin_price_rules.validation.unit_id_invalid'),
            'weekday.integer' => __('admin_price_rules.validation.weekday_invalid'),
            'weekday.between' => __('admin_price_rules.validation.weekday_invalid'),
            'start_time.required' => __('admin_price_rules.validation.start_time_invalid'),
            'start_time.date_format' => __('admin_price_rules.validation.start_time_invalid'),
            'end_time.required' => __('admin_price_rules.validation.end_time_invalid'),
            'end_time.date_format' => __('admin_price_rules.validation.end_time_invalid'),
            'end_time.after' => __('admin_price_rules.validation.end_after_start'),
            'is_peak.boolean' => __('admin_price_rules.validation.is_peak_invalid'),
            'price_per_hour.required' => __('admin_price_rules.validation.price_invalid'),
            'price_per_hour.integer' => __('admin_price_rules.validation.price_invalid'),
            'price_per_hour.min' => __('admin_price_rules.validation.price_invalid'),
            'price_per_hour.max' => __('admin_price_rules.validation.price_invalid'),
            'valid_from.required' => __('admin_price_rules.validation.valid_from_invalid'),
            'valid_from.date_format' => __('admin_price_rules.validation.valid_from_invalid'),
            'valid_to.date_format' => __('admin_price_rules.validation.valid_to_invalid'),
            'valid_to.after_or_equal' => __('admin_price_rules.validation.valid_to_after_from'),
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

            $this->rejectOverlap($validator, [
                'unit_id' => (int) $this->input('unit_id'),
                'weekday' => $this->input('weekday') === null ? null : (int) $this->input('weekday'),
                'start_time' => $this->input('start_time'),
                'end_time' => $this->input('end_time'),
                'valid_from' => $this->input('valid_from'),
                'valid_to' => $this->input('valid_to'),
            ]);
        }];
    }

    /**
     * Get the validated price rule attributes, defaulting `is_peak` to false on creation.
     *
     * @return array<string, mixed>
     */
    public function priceRuleAttributes(): array
    {
        return $this->withFullTimes($this->validated()) + ['is_peak' => false];
    }

    /**
     * Store times as `H:i:s` so an unchanged time is not seen as a change.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function withFullTimes(array $attributes): array
    {
        foreach (['start_time', 'end_time'] as $field) {
            if (isset($attributes[$field])) {
                $attributes[$field] .= ':00';
            }
        }

        return $attributes;
    }

    /**
     * Add an error on `start_time` when the merged values overlap another rule of the same unit.
     *
     * @param  array{unit_id: int, weekday: int|null, start_time: string, end_time: string, valid_from: string, valid_to: string|null}  $values
     */
    protected function rejectOverlap(Validator $validator, array $values, ?int $ignoreId = null): void
    {
        $conflict = app(PriceRuleOverlapChecker::class)->findOverlap(
            $values['unit_id'],
            $values['weekday'],
            $values['start_time'],
            $values['end_time'],
            $values['valid_from'],
            $values['valid_to'],
            $ignoreId,
        );

        if ($conflict !== null) {
            $validator->errors()->add('start_time', __('admin_price_rules.validation.overlap', ['id' => $conflict->id]));
        }
    }

    /**
     * Build a rule that accepts only times falling on a half-hour boundary.
     */
    protected function halfHourStep(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && (int) substr($value, 3, 2) % 30 !== 0) {
                $fail(__('admin_price_rules.validation.time_step'));
            }
        };
    }
}
