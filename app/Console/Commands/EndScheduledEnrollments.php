<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Services\Academies\EnrollmentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('enrollments:end-scheduled')]
#[Description('End enrollments whose cancellation was scheduled for the end of the paid period')]
class EndScheduledEnrollments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EnrollmentService $enrollments): int
    {
        $ended = 0;

        Enrollment::query()
            ->where('status', 'active')
            ->whereNotNull('ended_at')
            ->where('ended_at', '<=', now())
            ->each(function (Enrollment $enrollment) use ($enrollments, &$ended): void {
                $ended += $enrollments->endScheduled($enrollment) ? 1 : 0;
            });

        $this->info("Ended {$ended} enrollments.");

        return self::SUCCESS;
    }
}
