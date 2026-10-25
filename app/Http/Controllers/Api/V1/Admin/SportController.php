<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\DeletesWithoutBreakingReferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Sports\IndexSportRequest;
use App\Http\Requests\Api\V1\Admin\Sports\StoreSportRequest;
use App\Http\Requests\Api\V1\Admin\Sports\UpdateSportRequest;
use App\Http\Resources\SportResource;
use App\Models\Sport;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SportController extends Controller
{
    use DeletesWithoutBreakingReferences;

    /**
     * List sports ordered by id, optionally filtered by active state.
     */
    public function index(IndexSportRequest $request): AnonymousResourceCollection
    {
        $sports = Sport::query()
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('id')
            ->get();

        return SportResource::collection($sports);
    }

    /**
     * Create a sport.
     */
    public function store(StoreSportRequest $request, AuditLogger $audit): JsonResponse
    {
        $sport = Sport::create($request->validated());
        $sport->refresh();

        $audit->log('sport.created', $sport, null, $sport->toArray());

        return response()->json([
            'message' => __('admin_sports.sport_created'),
            'data' => new SportResource($sport),
        ], 201);
    }

    /**
     * Show one sport.
     */
    public function show(Sport $sport): SportResource
    {
        return new SportResource($sport);
    }

    /**
     * Update a sport.
     */
    public function update(UpdateSportRequest $request, Sport $sport, AuditLogger $audit): JsonResponse
    {
        $sport->update($request->validated());

        if ($sport->wasChanged()) {
            $audit->logChange('sport.updated', $sport);
        }

        return response()->json([
            'message' => __('admin_sports.sport_updated'),
            'data' => new SportResource($sport),
        ]);
    }

    /**
     * Delete a sport that nothing references.
     *
     * @throws ConflictHttpException when coaches, academies, bookable units, bookings or discounts still reference the sport
     */
    public function destroy(Sport $sport, AuditLogger $audit): JsonResponse
    {
        $before = $sport->toArray();

        $this->deleteOrConflict($sport, __('admin_sports.sport_in_use'));

        $audit->log('sport.deleted', $sport, $before, null);

        return response()->json(['message' => __('admin_sports.sport_deleted')]);
    }
}
