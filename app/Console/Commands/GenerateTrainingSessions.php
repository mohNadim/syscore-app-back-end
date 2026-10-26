<?php

namespace App\Console\Commands;

use App\Exceptions\ScheduleConflictException;
use App\Models\GroupSchedule;
use App\Services\Academies\TrainingSessionGenerator;
use App\Services\DisplayTimezone;
use App\Services\SettingStore;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sessions:generate')]
#[Description('Generate training sessions of active group schedules for the rolling window')]
class GenerateTrainingSessions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TrainingSessionGenerator $generator, DisplayTimezone $timezone, SettingStore $settings): int
    {
        $today = CarbonImmutable::now($timezone->name())->startOfDay();
        $until = $today->addDays((int) $settings->get('academy.session_generation_days'));
        $created = 0;
        $conflicted = 0;

        GroupSchedule::query()
            ->whereHas('group', fn ($query) => $query->where('is_active', true))
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $today))
            ->each(function (GroupSchedule $schedule) use ($generator, $today, $until, &$created, &$conflicted): void {
                try {
                    $created += count($generator->generate($schedule, $today, $until)->created);
                } catch (ScheduleConflictException) {
                    $conflicted++;
                    $this->warn("Schedule {$schedule->id} has conflicting dates and was skipped.");
                }
            });

        $this->info("Created {$created} sessions; {$conflicted} schedules skipped for conflicts.");

        return self::SUCCESS;
    }
}
