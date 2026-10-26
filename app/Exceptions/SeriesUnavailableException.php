<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Thrown when some dates of a recurring booking cannot be booked and the customer did not agree to skip them.
 *
 * Rendered as a 409 with `message` and `unavailable` by the global handler in bootstrap/app.php.
 */
class SeriesUnavailableException extends ConflictHttpException
{
    /**
     * @param  list<array{date: string, status: string}>  $unavailable  the local dates that cannot be booked with the reason
     */
    public function __construct(public readonly array $unavailable)
    {
        parent::__construct(__('booking_series.some_unavailable'));
    }
}
