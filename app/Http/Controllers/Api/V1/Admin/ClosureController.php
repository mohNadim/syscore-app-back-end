<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Closures\IndexClosureRequest;
use App\Http\Requests\Api\V1\Admin\Closures\StoreClosureRequest;
use App\Http\Requests\Api\V1\Admin\Closures\UpdateClosureRequest;
use App\Http\Resources\ClosureResource;
use App\Models\Closure;
use App\Services\AuditLogger;
use App\Services\DisplayTimezone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClosureController extends Controller
{
    /**
     * List closures, newest start first, optionally filtered by target and by a local date range.
     */
    public function index(IndexClosureRequest $request, DisplayTimezone $timezone): AnonymousResourceCollection
    {
        $closures = Closure::query()
            ->with(['facility', 'space'])
            ->when($request->filled('facility_id'), fn ($query) => $query->where('facility_id', $request->integer('facility_id')))
            ->when($request->filled('space_id'), fn ($query) => $query->where('space_id', $request->integer('space_id')))
            ->when($request->filled('from'), fn ($query) => $query->where('ends_at', '>=', $timezone->dayStartUtc($request->string('from')->toString())))
            ->when($request->filled('to'), fn ($query) => $query->where('starts_at', '<=', $timezone->dayEndUtc($request->string('to')->toString())))
            ->when($request->boolean('upcoming'), fn ($query) => $query->where('ends_at', '>=', now()))
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ClosureResource::collection($closures);
    }

    /**
     * Create a closure for one facility or one space.
     */
    public function store(StoreClosureRequest $request, AuditLogger $audit): JsonResponse
    {
        $closure = Closure::create([
            ...$request->closureAttributes(),
            'created_by_staff_id' => $request->user()->id,
        ])->refresh();

        $audit->log('closure.created', $closure, null, $closure->toArray());

        return response()->json([
            'message' => __('admin_hours.closure_created'),
            'data' => new ClosureResource($closure->load(['facility', 'space'])),
        ], 201);
    }

    /**
     * Show one closure.
     */
    public function show(Closure $closure): ClosureResource
    {
        return new ClosureResource($closure->load(['facility', 'space']));
    }

    /**
     * Update a closure.
     */
    public function update(UpdateClosureRequest $request, Closure $closure, AuditLogger $audit): JsonResponse
    {
        $closure->update($request->closureAttributes());

        if ($closure->wasChanged()) {
            $audit->logChange('closure.updated', $closure);
        }

        return response()->json([
            'message' => __('admin_hours.closure_updated'),
            'data' => new ClosureResource($closure->load(['facility', 'space'])),
        ]);
    }

    /**
     * Delete a closure permanently.
     */
    public function destroy(Closure $closure, AuditLogger $audit): JsonResponse
    {
        $before = $closure->toArray();

        $closure->delete();

        $audit->log('closure.deleted', $closure, $before, null);

        return response()->json(['message' => __('admin_hours.closure_deleted')]);
    }
}
