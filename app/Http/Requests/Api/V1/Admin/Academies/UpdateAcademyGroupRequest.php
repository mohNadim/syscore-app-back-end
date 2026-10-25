<?php

namespace App\Http\Requests\Api\V1\Admin\Academies;

use App\Models\AcademyGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class UpdateAcademyGroupRequest extends StoreAcademyGroupRequest
{
    /**
     * Get the validation rules that apply to the request; every field is optional on update.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = collect(parent::rules())
            ->map(fn (array $rules) => ['bail', 'sometimes', ...array_values(array_filter($rules, fn ($rule) => ! in_array($rule, ['bail', 'sometimes'], true)))])
            ->all();

        $rules['age_max'] = ['bail', 'sometimes', 'integer', 'between:0,30'];

        return $rules;
    }

    /**
     * Cross-field checks against the stored group: age range and capacity versus active enrollments.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var AcademyGroup $group */
                $group = $this->route('group');

                $ageMin = $this->has('age_min') ? (int) $this->input('age_min') : $group->age_min;
                $ageMax = $this->has('age_max') ? (int) $this->input('age_max') : $group->age_max;

                if ($ageMax < $ageMin) {
                    $validator->errors()->add('age_max', __('admin_academies.validation.age_max_below_min'));
                }

                if ($this->has('capacity')) {
                    $enrolled = $group->enrollments()->where('status', 'active')->count();

                    if ((int) $this->input('capacity') < $enrolled) {
                        $validator->errors()->add('capacity', __('admin_academies.validation.capacity_below_enrolled', ['count' => $enrolled]));
                    }
                }
            },
        ];
    }
}
