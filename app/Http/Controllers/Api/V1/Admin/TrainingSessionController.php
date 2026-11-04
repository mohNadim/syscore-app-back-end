<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Sessions\CancelTrainingSessionRequest;
use App\Http\Requests\Api\V1\Admin\Sessions\IndexTrainingSessionRequest;
use App\Http\Requests\Api\V1\Admin\Sessions\UpdateTrainingSessionRequest;
use App\Http\Resources\TrainingSessionResource;
use App\Models\BookableUnit;
use App\Models\TrainingSession;
use App\Services\AuditLogger;
use App\Services\DisplayTimezone;
use App\Services\Notifications\NotificationEvents;
use App\Services\Reservations\OperatingHoursChecker;
use App\Services\Reservations\SpaceReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TrainingSessionController extends Controller
{
    /**
     * List sessions by start time, filtered by academy, group, unit, coach, status and a local date range.
     */
    public function index(IndexTrainingSessionRequest $request, DisplayTimezone $timezone): AnonymousResourceCollection
    {
        $sessions = TrainingSession::query()
            ->with(['group.academy', 'unit', 'coach'])
            ->when($request->filled('academy_id'), fn ($query) => $query->whereHas('group', fn ($group) => $group->where('academy_id', $request->integer('academy_id'))))
            ->when($request->filled('group_id'), fn ($query) => $query->where('group_id', $request->integer('group_id')))
            ->when($request->filled('unit_id'), fn ($query) => $query->where('unit_id', $request->integer('unit_id')))
            ->when($request->filled('coach_id'), fn ($query) => $query->where('coach_id', $request->integer('coach_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('from'), fn ($query) => $query->where('starts_at', '>=', $timezone->dayStartUtc($request->string('from')->toString())))
            ->when($request->filled('to'), fn ($query) => $query->where('starts_at', '<=', $timezone->dayEndUtc($request->string('to')->toString())))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15))->withQueryString();

        return TrainingSessionResource::collection($sessions);
    }

    /**
     * Show one session with its group (and academy), unit and coach.
     */
    public function show(TrainingSession $trainingSession): TrainingSessionResource
    {
        return new TrainingSessionResource($trainingSession->load(['group.academy', 'unit', 'coach']));
    }

    /**
     * Reschedule and/or reassign a session that has not started (sent as PATCH).
     *
     * Spaces of the target unit are locked first, then closures (422) and conflicts ignoring this session
     * (409 slot_taken) are checked; the old reservations are deactivated and new ones created.
     *
     * Guardians of the group are notified only when the time or room really changed.
     *
     * @throws ConflictHttpException when the session is cancelled, done or already started, or the slot is taken
     * @throws ValidationException when the facility or a space is closed in the new interval, or it lies outside the operating hours
     */
    public function update(
        UpdateTrainingSessionRequest $request,
        TrainingSession $trainingSession,
        SpaceReservationService $reservations,
        OperatingHoursChecker $hours,
        DisplayTimezone $timezone,
        AuditLogger $audit,
        NotificationEvents $events,
    ): JsonResponse {
        $before = $trainingSession->toArray();

        $moved = DB::transaction(function () use ($request, $trainingSession, $reservations, $hours, $timezone): bool {
            $session = TrainingSession::query()->lockForUpdate()->findOrFail($trainingSession->id);
            $this->ensureChangeable($session);

            $startsAt = $request->filled('starts_at') ? $timezone->localToUtc($request->string('starts_at')->toString()) : $session->starts_at;
            $endsAt = $request->filled('ends_at') ? $timezone->localToUtc($request->string('ends_at')->toString()) : $session->ends_at;
            $unitId = $request->filled('unit_id') ? $request->integer('unit_id') : $session->unit_id;

            $moved = $unitId !== $session->unit_id || ! $startsAt->equalTo($session->starts_at) || ! $endsAt->equalTo($session->ends_at);

            if ($moved) {
                $unit = BookableUnit::query()->with(['spaces', 'facility'])->findOrFail($unitId);
                $spaceIds = $unit->spaces->modelKeys();

                $reservations->lockSpaces($spaceIds);

                if ($reservations->closed($unit->facility_id, $spaceIds, $startsAt, $endsAt)) {
                    throw ValidationException::withMessages(['starts_at' => [__('admin_sessions.closed')]]);
                }

                if (! $hours->within($unit->facility_id, $startsAt, $endsAt)) {
                    throw ValidationException::withMessages(['starts_at' => [__('admin_sessions.outside_operating_hours')]]);
                }

                if ($reservations->conflicts($spaceIds, $startsAt, $endsAt, $session->id)->isNotEmpty()) {
                    throw new ConflictHttpException(__('admin_sessions.slot_taken'));
                }

                $reservations->releaseForSession($session);
            }

            $session->original_starts_at ??= $session->starts_at;
            $session->fill([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'unit_id' => $unitId,
                'status' => 'changed',
                'change_reason' => $request->string('change_reason')->toString(),
            ]);

            if ($request->has('coach_id')) {
                $session->coach_id = $request->input('coach_id');
            }

            $session->save();

            if ($moved) {
                $reservations->reserveForSession($session, $spaceIds);
            }

            $trainingSession->setRawAttributes($session->getAttributes(), true);
            $trainingSession->setRelations([]);

            return $moved;
        });

        $audit->log('training_session.updated', $trainingSession, $before, $trainingSession->toArray());

        if ($moved) {
            $events->sessionUpdated($trainingSession);
        }

        return response()->json([
            'message' => __('admin_sessions.session_updated'),
            'data' => new TrainingSessionResource($trainingSession->load(['group.academy', 'unit', 'coach'])),
        ]);
    }

    /**
     * Cancel a session that has not started and free its spaces.
     *
     * Guardians of the group are notified of the cancellation.
     *
     * @throws ConflictHttpException when the session is cancelled, done or already started
     */
    public function cancel(
        CancelTrainingSessionRequest $request,
        TrainingSession $trainingSession,
        SpaceReservationService $reservations,
        AuditLogger $audit,
        NotificationEvents $events,
    ): JsonResponse {
        $before = $trainingSession->toArray();

        DB::transaction(function () use ($request, $trainingSession, $reservations): void {
            $session = TrainingSession::query()->lockForUpdate()->findOrFail($trainingSession->id);
            $this->ensureChangeable($session);

            $session->update([
                'status' => 'cancelled',
                'change_reason' => $request->string('reason')->toString(),
            ]);
            $reservations->releaseForSession($session);

            $trainingSession->setRawAttributes($session->getAttributes(), true);
        });

        $audit->log('training_session.cancelled', $trainingSession, $before, $trainingSession->toArray());
        $events->sessionCancelled($trainingSession);

        return response()->json([
            'message' => __('admin_sessions.session_cancelled'),
            'data' => new TrainingSessionResource($trainingSession->load(['group.academy', 'unit', 'coach'])),
        ]);
    }

    /**
     * Only scheduled or changed sessions that have not started can be modified.
     *
     * @throws ConflictHttpException
     */
    protected function ensureChangeable(TrainingSession $session): void
    {
        if (! in_array($session->status, ['scheduled', 'changed'], true) || $session->starts_at->lte(now())) {
            throw new ConflictHttpException(__('admin_sessions.session_locked'));
        }
    }
}
