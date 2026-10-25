<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Services\Otp\PhoneNumber;

trait NormalizesPhone
{
    /**
     * Normalize the phone number to +9639… before validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
        }
    }
}
