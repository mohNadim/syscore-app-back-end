<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\DeletesWithoutBreakingReferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Facilities\StoreFacilityRequest;
use App\Http\Requests\Api\V1\Admin\Facilities\UpdateFacilityRequest;
use App\Http\Resources\FacilityResource;
use App\Models\Facility;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FacilityController extends Controller
{
    use DeletesWithoutBreakingReferences;

    /**
     * List facilities with their spaces and bookable units, optionally filtered by type and active state.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $facilities = Facility::query()
            ->with(['spaces', 'units.spaces', 'units.sports'])
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->query('type')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return FacilityResource::collection($facilities);
    }

    /**
     * Create a facility.
     */
    public function store(StoreFacilityRequest $request, AuditLogger $audit): JsonResponse
    {
        $facility = Facility::create($request->validated());
        $facility->refresh();

        $audit->log('facility.created', $facility, null, $facility->toArray());

        return response()->json([
            'message' => __('admin_facilities.facility_created'),
            'data' => new FacilityResource($facility),
        ], 201);
    }

    /**
     * Show one facility with its spaces and bookable units.
     */
    public function show(Facility $facility): FacilityResource
    {
        return new FacilityResource($facility->load(['spaces', 'units.spaces', 'units.sports']));
    }

    /**
     * Update a facility.
     */
    public function update(UpdateFacilityRequest $request, Facility $facility, AuditLogger $audit): JsonResponse
    {
        $facility->update($request->validated());

        if ($facility->wasChanged()) {
            $audit->logChange('facility.updated', $facility);
        }

        return response()->json([
            'message' => __('admin_facilities.facility_updated'),
            'data' => new FacilityResource($facility),
        ]);
    }

    /**
     * Delete a facility that nothing references.
     *
     * @throws ConflictHttpException when spaces, units, operating hours or closures still reference the facility
     */
    public function destroy(Facility $facility, AuditLogger $audit): JsonResponse
    {
        $before = $facility->toArray();

        $this->deleteOrConflict($facility, __('admin_facilities.facility_in_use'));

        $audit->log('facility.deleted', $facility, $before, null);

        return response()->json(['message' => __('admin_facilities.facility_deleted')]);
    }
}
