<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChildSchedule\ChildAttendanceRequest;
use App\Http\Requests\Api\V1\ChildSchedule\ChildScheduleRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\ChildSessionResource;
use App\Models\Attendance;
use App\Models\Child;
use App\Models\TrainingSession;
use App\Services\DisplayTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ChildScheduleController extends Controller
{
    /**
     * List the sessions of the groups the child is actively enrolled in, with the child's attendance status.
     *
     * The range is local dates and defaults to today and the next 13 days; cancelled sessions are kept so the parent sees them.
     */
    public function schedule(ChildScheduleRequest $request, Child $child, DisplayTimezone $timezone): AnonymousResourceCollection
    {
        Gate::authorize('view', $child);

        $today = now($timezone->name())->startOfDay();
        $from = $request->string('from')->toString() ?: $today->toDateString();
        $to = $request->string('to')->toString() ?: $today->addDays(13)->toDateString();

        $sessions = TrainingSession::query()
            ->with([
                'group.academy',
                'unit',
                'coach',
                'attendance' => fn ($query) => $query->where('child_id', $child->id),
            ])
            ->whereIn('group_id', $child->enrollments()->where('status', 'active')->select('group_id'))
            ->whereBetween('starts_at', [$timezone->dayStartUtc($from), $timezone->dayEndUtc($to)])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        return ChildSessionResource::collection($sessions);
    }

    /**
     * List the child's attendance records, newest session first, with a summary over the same date range.
     */
    public function attendance(ChildAttendanceRequest $request, Child $child, DisplayTimezone $timezone): AnonymousResourceCollection
    {
        Gate::authorize('view', $child);

        $inRange = fn (Builder $query) => $query
            ->when($request->filled('from'), fn ($sessions) => $sessions->where('starts_at', '>=', $timezone->dayStartUtc($request->string('from')->toString())))
            ->when($request->filled('to'), fn ($sessions) => $sessions->where('starts_at', '<=', $timezone->dayEndUtc($request->string('to')->toString())));

        $records = Attendance::query()
            ->select('attendance.*')
            ->join('training_sessions', 'training_sessions.id', '=', 'attendance.session_id')
            ->where('attendance.child_id', $child->id)
            ->whereHas('session', $inRange)
            ->with('session.group.academy')
            ->orderByDesc('training_sessions.starts_at')
            ->orderByDesc('attendance.id')
            ->paginate($request->integer('per_page', 15))->withQueryString();

        $counts = Attendance::query()
            ->where('child_id', $child->id)
            ->whereHas('session', $inRange)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $present = (int) ($counts['present'] ?? 0);
        $absent = (int) ($counts['absent'] ?? 0);
        $excused = (int) ($counts['excused'] ?? 0);
        $total = $present + $absent;

        return AttendanceResource::collection($records)->additional([
            'summary' => [
                'present' => $present,
                'absent' => $absent,
                'excused' => $excused,
                'total' => $total,
                'attendance_rate' => $total > 0 ? round($present / $total * 100, 1) : null,
            ],
        ]);
    }
}
