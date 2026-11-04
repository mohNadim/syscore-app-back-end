<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Thrown when a request is well formed but a business rule forbids it.
 *
 * Rendered as a 422 with `message` and a machine readable `code` by the global handler in bootstrap/app.php.
 */
class BusinessRuleException extends UnprocessableEntityHttpException
{
    /**
     * @param  string  $errorCode  stable snake_case identifier the mobile app can switch on
     * @param  string  $message  the translated, human readable message
     */
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
