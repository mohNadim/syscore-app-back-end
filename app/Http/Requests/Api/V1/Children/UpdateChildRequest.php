<?php

namespace App\Http\Requests\Api\V1\Children;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateChildRequest extends StoreChildRequest
{
    /**
     * Get the validation rules that apply to the request; every field is optional on update.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return collect(parent::rules())
            ->map(fn (array $rules, string $field) => $field === 'health_notes' ? $rules : ['sometimes', ...$rules])
            ->all();
    }
}
