<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Attendance\IndexAttendanceSessionRequest;
use App\Http\Requests\Api\V1\Admin\Attendance\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\AttendanceRosterEntryResource;
use App\Http\Resources\AttendanceSessionResource;
use App\Models\Attendance;
use App\Models\Child;
use App\Models\Enrollment;
use App\Models\TrainingSession;
use App\Services\AuditLogger;
use App\Services\DisplayTimezone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AttendanceController extends Controller
{
    /**
     * List the non-cancelled sessions of a local day with their attendance counters.
     */
    public function index(IndexAttendanceSessionRequest $request, DisplayTimezone $timezone): AnonymousResourceCollection
    {
        $date = $request->string('date')->toString();

        $sessions = TrainingSession::query()
            ->with(['group.academy', 'unit', 'coach'])
            ->withCount([
                'attendance as present_count' => fn ($query) => $query->where('status', 'present'),
                'attendance as absent_count' => fn ($query) => $query->where('status', 'absent'),
            ])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('starts_at', [$timezone->dayStartUtc($date), $timezone->dayEndUtc($date)])
            ->when($request->filled('group_id'), fn ($query) => $query->where('group_id', $request->integer('group_id')))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        $enrolled = Enrollment::query()
            ->where('status', 'active')
            ->whereIn('group_id', $sessions->pluck('group_id')->unique())
            ->selectRaw('group_id, count(*) as total')
            ->groupBy('group_id')
            ->pluck('total', 'group_id');

        $sessions->each(fn (TrainingSession $session) => $session->setAttribute('enrolled_count', $enrolled[$session->group_id] ?? 0));

        return AttendanceSessionResource::collection($sessions);
    }

    /**
     * Show the active children of the session's group with their recorded status.
     */
    public function show(TrainingSession $trainingSession): AnonymousResourceCollection
    {
        $children = Child::query()
            ->whereHas('enrollments', fn ($query) => $query->where('group_id', $trainingSession->group_id)->where('status', 'active'))
            ->with(['attendance' => fn ($query) => $query->where('session_id', $trainingSession->id)])
            ->orderBy('full_name')
            ->orderBy('id')
            ->get();

        return AttendanceRosterEntryResource::collection($children);
    }

    /**
     * Mark (or correct) the attendance of enrolled children for a session that has started.
     *
     * The call is an upsert per child, so the same call can be repeated to fix a mistake.
     *
     * @throws ConflictHttpException when the session is cancelled or has not started yet
     * @throws ValidationException when a child is not actively enrolled in the session's group
     */
    public function update(UpdateAttendanceRequest $request, TrainingSession $trainingSession, AuditLogger $audit): JsonResponse
    {
        $staff = $request->user();
        $records = collect($request->validated('records'));

        if ($trainingSession->status === 'cancelled' || $trainingSession->starts_at->gt(now())) {
            throw new ConflictHttpException(__('admin_attendance.session_not_markable'));
        }

        $enrolledIds = Enrollment::query()
            ->where('group_id', $trainingSession->group_id)
            ->where('status', 'active')
            ->whereIn('child_id', $records->pluck('child_id'))
            ->pluck('child_id');

        $records->each(function (array $record, int $index) use ($enrolledIds): void {
            if (! $enrolledIds->contains($record['child_id'])) {
                throw ValidationException::withMessages(["records.{$index}.child_id" => [__('admin_attendance.child_not_enrolled')]]);
            }
        });

        $saved = DB::transaction(fn () => $records->map(fn (array $record): Attendance => Attendance::query()->updateOrCreate(
            ['session_id' => $trainingSession->id, 'child_id' => $record['child_id']],
            ['status' => $record['status'], 'marked_by_staff_id' => $staff->id, 'marked_at' => now()],
        )));

        $audit->log('attendance.marked', $trainingSession, null, ['records' => $records->all()]);

        return response()->json([
            'message' => __('admin_attendance.attendance_saved'),
            'data' => AttendanceResource::collection($saved),
        ]);
    }
}
