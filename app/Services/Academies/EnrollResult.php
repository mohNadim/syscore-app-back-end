<?php

namespace App\Services\Academies;

use App\Models\Enrollment;
use App\Models\WaitlistEntry;

readonly class EnrollResult
{
    /**
     * Exactly one of the two is set: an enrollment when a seat was taken, a waitlist entry when the group is full.
     */
    public function __construct(
        public ?Enrollment $enrollment = null,
        public ?WaitlistEntry $waitlistEntry = null,
    ) {}

    /**
     * Whether the child got a seat.
     */
    public function isEnrolled(): bool
    {
        return $this->enrollment !== null;
    }
}
