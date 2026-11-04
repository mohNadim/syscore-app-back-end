<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AuditLogs\IndexAuditLogRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\DisplayTimezone;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    /**
     * Relations loaded for every audit log response.
     *
     * @var list<string>
     */
    private const WITH = ['actorUser', 'actorStaff'];

    /**
     * List audit log entries, newest first, filtered by actor, action, audited entity and local date range.
     */
    public function index(IndexAuditLogRequest $request, DisplayTimezone $timezone): AnonymousResourceCollection
    {
        $auditLogs = AuditLog::query()
            ->with(self::WITH)
            ->when($request->filled('actor_type'), fn ($query) => $query->where('actor_type', $request->string('actor_type')->toString()))
            ->when($request->filled('actor_user_id'), fn ($query) => $query->where('actor_user_id', $request->integer('actor_user_id')))
            ->when($request->filled('actor_staff_id'), fn ($query) => $query->where('actor_staff_id', $request->integer('actor_staff_id')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')->toString()))
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->string('entity_type')->toString()))
            ->when($request->filled('entity_id'), fn ($query) => $query->where('entity_id', $request->integer('entity_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->where('created_at', '>=', $timezone->dayStartUtc($request->string('date_from')->toString())))
            ->when($request->filled('date_to'), fn ($query) => $query->where('created_at', '<=', $timezone->dayEndUtc($request->string('date_to')->toString())))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))->withQueryString();

        return AuditLogResource::collection($auditLogs);
    }

    /**
     * Show one audit log entry with its actor.
     */
    public function show(AuditLog $auditLog): AuditLogResource
    {
        return new AuditLogResource($auditLog->load(self::WITH));
    }
}
