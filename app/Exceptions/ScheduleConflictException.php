<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Thrown when generating training sessions would overlap existing space reservations.
 *
 * Rendered as a 409 with `message` and `conflicts` by the global handler in bootstrap/app.php.
 */
class ScheduleConflictException extends ConflictHttpException
{
    /**
     * @param  list<array{date: string, starts_at: string, ends_at: string}>  $conflicts  the clashing local dates with their local ISO start and end
     */
    public function __construct(public readonly array $conflicts)
    {
        parent::__construct(__('admin_schedules.schedule_conflict', ['count' => count($conflicts)]));
    }
}
