<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\DeletesWithoutBreakingReferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Spaces\StoreSpaceRequest;
use App\Http\Requests\Api\V1\Admin\Spaces\UpdateSpaceRequest;
use App\Http\Resources\SpaceResource;
use App\Models\Facility;
use App\Models\Space;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SpaceController extends Controller
{
    use DeletesWithoutBreakingReferences;

    /**
     * List the spaces of one facility.
     */
    public function index(Facility $facility): AnonymousResourceCollection
    {
        return SpaceResource::collection($facility->spaces()->orderBy('id')->get());
    }

    /**
     * Create a space inside a facility.
     */
    public function store(StoreSpaceRequest $request, Facility $facility, AuditLogger $audit): JsonResponse
    {
        $space = $facility->spaces()->create($request->validated())->refresh();

        $audit->log('space.created', $space, null, $space->toArray());

        return response()->json([
            'message' => __('admin_facilities.space_created'),
            'data' => new SpaceResource($space),
        ], 201);
    }

    /**
     * Show one space.
     */
    public function show(Space $space): SpaceResource
    {
        return new SpaceResource($space);
    }

    /**
     * Update a space.
     */
    public function update(UpdateSpaceRequest $request, Space $space, AuditLogger $audit): JsonResponse
    {
        $space->update($request->validated());

        if ($space->wasChanged()) {
            $audit->logChange('space.updated', $space);
        }

        return response()->json([
            'message' => __('admin_facilities.space_updated'),
            'data' => new SpaceResource($space),
        ]);
    }

    /**
     * Delete a space that nothing references.
     *
     * @throws ConflictHttpException when units, reservations or closures still reference the space
     */
    public function destroy(Space $space, AuditLogger $audit): JsonResponse
    {
        $before = $space->toArray();

        $this->deleteOrConflict($space, __('admin_facilities.space_in_use'));

        $audit->log('space.deleted', $space, $before, null);

        return response()->json(['message' => __('admin_facilities.space_deleted')]);
    }
}
