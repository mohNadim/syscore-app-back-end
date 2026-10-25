<?php

namespace App\Http\Requests\Api\V1\Admin\Sessions;

use App\Models\BookableUnit;
use App\Models\TrainingSession;
use App\Services\DisplayTimezone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTrainingSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request; starts_at and ends_at must be sent together.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['bail', 'required_with:ends_at', 'date_format:Y-m-d H:i'],
            'ends_at' => ['bail', 'required_with:starts_at', 'date_format:Y-m-d H:i', 'after:starts_at'],
            'unit_id' => ['bail', 'sometimes', 'integer', 'exists:bookable_units,id'],
            'coach_id' => ['bail', 'sometimes', 'nullable', 'integer', 'exists:coaches,id'],
            'change_reason' => ['bail', 'required', 'string', 'max:255'],
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
            'starts_at.required_with' => __('admin_sessions.validation.times_together'),
            'starts_at.date_format' => __('admin_sessions.validation.datetime_invalid'),
            'ends_at.required_with' => __('admin_sessions.validation.times_together'),
            'ends_at.date_format' => __('admin_sessions.validation.datetime_invalid'),
            'ends_at.after' => __('admin_sessions.validation.ends_before_starts'),
            'unit_id.integer' => __('admin_sessions.validation.unit_id_invalid'),
            'unit_id.exists' => __('admin_sessions.validation.unit_id_invalid'),
            'coach_id.integer' => __('admin_sessions.validation.coach_id_invalid'),
            'coach_id.exists' => __('admin_sessions.validation.coach_id_invalid'),
            'change_reason.required' => __('admin_sessions.validation.change_reason_required'),
            'change_reason.string' => __('admin_sessions.validation.change_reason_invalid'),
            'change_reason.max' => __('admin_sessions.validation.change_reason_invalid'),
        ];
    }

    /**
     * The new start must lie in the future and a new unit must be active and support the academy sport.
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

                if ($this->filled('starts_at') && app(DisplayTimezone::class)->localToUtc($this->string('starts_at')->toString())->lte(now())) {
                    $validator->errors()->add('starts_at', __('admin_sessions.validation.starts_in_past'));
                }

                if (! $this->filled('unit_id')) {
                    return;
                }

                /** @var TrainingSession $session */
                $session = $this->route('training_session');
                $unit = BookableUnit::query()->with('facility')->find($this->integer('unit_id'));

                if ($unit === null) {
                    return;
                }

                if (! $unit->is_active || ! $unit->facility->is_active) {
                    $validator->errors()->add('unit_id', __('admin_sessions.validation.unit_inactive'));
                } elseif (! $unit->sports()->whereKey($session->group->academy->sport_id)->exists()) {
                    $validator->errors()->add('unit_id', __('admin_sessions.validation.unit_sport_mismatch'));
                }
            },
        ];
    }
}
