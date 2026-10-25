<?php

namespace App\Http\Requests\Api\V1\Admin\Discounts;

use App\Models\Discount;
use App\Services\DisplayTimezone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class UpdateDiscountRequest extends StoreDiscountRequest
{
    /**
     * Get the validation rules that apply to the request; every field is optional on update.
     * The date order and the time window are checked against the merged values in `after()`.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = collect(parent::rules())
            ->map(fn (array $rules) => ['bail', 'sometimes', ...array_values(array_filter($rules, fn ($rule) => ! in_array($rule, ['bail', 'sometimes', 'required_with:time_to', 'required_with:time_from', 'after:starts_at', 'after:time_from'], true)))])
            ->all();

        return $rules;
    }

    /**
     * Get the validation callbacks that run once the base rules have run.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $errors = $validator->errors();
                $discount = $this->currentDiscount();
                $timezone = app(DisplayTimezone::class);

                if (! $errors->has('starts_at') && ! $errors->has('ends_at')) {
                    $startsAt = $this->has('starts_at') ? $timezone->localToUtc($this->input('starts_at')) : $discount->starts_at;
                    $endsAt = $this->has('ends_at') ? $timezone->localToUtc($this->input('ends_at')) : $discount->ends_at;

                    if ($endsAt->lte($startsAt)) {
                        $errors->add('ends_at', __('admin_discounts.validation.ends_after_starts'));
                    }
                }

                if ($errors->has('time_from') || $errors->has('time_to')) {
                    return;
                }

                $timeFrom = $this->has('time_from') ? $this->input('time_from') : $this->storedTime($discount->time_from);
                $timeTo = $this->has('time_to') ? $this->input('time_to') : $this->storedTime($discount->time_to);

                if (($timeFrom === null) !== ($timeTo === null)) {
                    $errors->add($timeFrom === null ? 'time_from' : 'time_to', __('admin_discounts.validation.time_window_incomplete'));
                } elseif ($timeFrom !== null && $timeTo <= $timeFrom) {
                    $errors->add('time_to', __('admin_discounts.validation.time_to_after_from'));
                }
            },
        ];
    }

    protected function currentDiscount(): ?Discount
    {
        $discount = $this->route('discount');

        return $discount instanceof Discount ? $discount : null;
    }

    /**
     * Trim a stored TIME value (HH:MM:SS) to HH:MM.
     */
    private function storedTime(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }
}
