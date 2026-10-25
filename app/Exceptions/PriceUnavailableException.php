<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * Thrown when no price rule covers the requested interval; rendered as the standard 422 with `errors.starts_at`.
 */
class PriceUnavailableException extends ValidationException
{
    public function __construct()
    {
        $validator = validator([], []);
        $validator->errors()->add('starts_at', __('pricing.no_price'));

        parent::__construct($validator);
    }
}
