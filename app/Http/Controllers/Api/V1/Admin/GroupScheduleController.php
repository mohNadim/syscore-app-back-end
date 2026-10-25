<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Schedules\StoreGroupScheduleRequest;
use App\Http\Requests\Api\V1\Admin\Schedules\UpdateGroupScheduleRequest;
use App\Http\Resources\GroupScheduleResource;
use App\Models\AcademyGroup;
use App\Models\GroupSchedule;
use App\Models\SpaceReservation;
use App\Models\TrainingSession;
use App\Services\Academies\GenerationResult;
use App\Services\Academies\TrainingSessionGenerator;
use App\Services\AuditLogger;
use App\Services\DisplayTimezone;
use App\Services\SettingStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class GroupScheduleController extends Controller
{
    /**
     * List the weekly schedules of a group with their units.
     */
    public function index(AcademyGroup $group): AnonymousResourceCollection
    {
        return GroupScheduleResource::collection(
            $group->schedules()->with('unit')->orderBy('weekday')->orderBy('start_time')->orderBy('id')->get()
        );
    }

    /**
     * Create a schedule and generate its sessions for the coming window in one transaction.
     *
     * A clash with an existing reservation aborts everything and answers 409 with the conflicting dates.
     */
    public function store(
        StoreGroupScheduleRequest $request,
        AcademyGroup $group,
        TrainingSessionGenerator $generator,
        AuditLogger $audit,
    ): JsonResponse {
        [$schedule, $result] = DB::transaction(function () use ($request, $group, $generator): array {
            $schedule = $group->schedules()->create($request->validated());

            return [$schedule, $this->generateWindow($generator, $schedule)];
        });

        $audit->log('group_schedule.created', $schedule, null, $schedule->refresh()->toArray());

        return response()->json([
            'message' => __('admin_schedules.schedule_created'),
            'data' => new GroupScheduleResource($schedule->load('unit')),
            ...$this->generationPayload($result),
        ], 201);
    }

    /**
     * Show one schedule with its unit.
     */
    public function show(GroupSchedule $schedule): GroupScheduleResource
    {
        return new GroupScheduleResource($schedule->load('unit'));
    }

    /**
     * Update a schedule: remove its future untouched sessions, save the change and regenerate from today.
     *
     * Sessions that are changed, cancelled, done, already started or have attendance are kept.
     * A clash answers 409 and nothing changes.
     */
    public function update(
        UpdateGroupScheduleRequest $request,
        GroupSchedule $schedule,
        TrainingSessionGenerator $generator,
        AuditLogger $audit,
    ): JsonResponse {
        $result = DB::transaction(function () use ($request, $schedule, $generator): GenerationResult {
            $this->deleteFutureUntouchedSessions($schedule);
            $schedule->update($request->validated());

            return $this->generateWindow($generator, $schedule->refresh());
        });

        if ($schedule->wasChanged()) {
            $audit->logChange('group_schedule.updated', $schedule);
        }

        return response()->json([
            'message' => __('admin_schedules.schedule_updated'),
            'data' => new GroupScheduleResource($schedule->load('unit')),
            ...$this->generationPayload($result),
        ]);
    }

    /**
     * Delete a schedule and its future untouched sessions; when other sessions remain, end it yesterday instead.
     */
    public function destroy(GroupSchedule $schedule, DisplayTimezone $timezone, AuditLogger $audit): JsonResponse
    {
        $before = $schedule->toArray();

        $ended = DB::transaction(function () use ($schedule, $timezone): bool {
            $this->deleteFutureUntouchedSessions($schedule);

            if (! $schedule->trainingSessions()->exists()) {
                $schedule->delete();

                return false;
            }

            $yesterday = CarbonImmutable::now($timezone->name())->subDay()->toDateString();
            $schedule->update(['valid_to' => $yesterday]);

            return true;
        });

        if ($ended) {
            $audit->log('group_schedule.ended', $schedule, $before, $schedule->refresh()->toArray());

            return response()->json([
                'message' => __('admin_schedules.schedule_ended'),
                'data' => new GroupScheduleResource($schedule->load('unit')),
            ]);
        }

        $audit->log('group_schedule.deleted', $schedule, $before, null);

        return response()->json(['message' => __('admin_schedules.schedule_deleted')]);
    }

    /**
     * Generate sessions from today (local) for the configured number of days.
     */
    protected function generateWindow(TrainingSessionGenerator $generator, GroupSchedule $schedule): GenerationResult
    {
        $today = CarbonImmutable::now(app(DisplayTimezone::class)->name())->startOfDay();
        $days = (int) app(SettingStore::class)->get('academy.session_generation_days');

        return $generator->generate($schedule, $today, $today->addDays($days));
    }

    /**
     * Delete the schedule's future scheduled sessions without attendance, releasing their reservations first.
     */
    protected function deleteFutureUntouchedSessions(GroupSchedule $schedule): void
    {
        $ids = TrainingSession::query()
            ->where('schedule_id', $schedule->id)
            ->where('status', 'scheduled')
            ->where('starts_at', '>=', now())
            ->whereDoesntHave('attendance')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        SpaceReservation::query()->whereIn('session_id', $ids)->delete();
        TrainingSession::query()->whereKey($ids)->delete();
    }

    /**
     * @return array{generated_sessions: int, skipped_closed_dates: list<string>}
     */
    protected function generationPayload(GenerationResult $result): array
    {
        return [
            'generated_sessions' => count($result->created),
            'skipped_closed_dates' => $result->skippedClosed,
        ];
    }
}
