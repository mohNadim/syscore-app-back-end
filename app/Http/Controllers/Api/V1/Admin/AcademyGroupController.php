<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\DeletesWithoutBreakingReferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Academies\StoreAcademyGroupRequest;
use App\Http\Requests\Api\V1\Admin\Academies\UpdateAcademyGroupRequest;
use App\Http\Resources\AcademyGroupResource;
use App\Models\Academy;
use App\Models\AcademyGroup;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AcademyGroupController extends Controller
{
    use DeletesWithoutBreakingReferences;

    /**
     * List the groups of an academy with their coach.
     */
    public function index(Academy $academy): AnonymousResourceCollection
    {
        return AcademyGroupResource::collection(
            $academy->groups()->with('coach')->orderBy('id')->get()
        );
    }

    /**
     * Create a group inside an academy.
     */
    public function store(StoreAcademyGroupRequest $request, Academy $academy, AuditLogger $audit): JsonResponse
    {
        $group = $academy->groups()->create($request->validated());
        $group->refresh()->load('coach');

        $audit->log('academy_group.created', $group, null, $group->toArray());

        return response()->json([
            'message' => __('admin_academies.group_created'),
            'data' => new AcademyGroupResource($group),
        ], 201);
    }

    /**
     * Show one group with its academy, coach and schedules (with their units).
     */
    public function show(AcademyGroup $group): AcademyGroupResource
    {
        return new AcademyGroupResource($group->load(['academy', 'coach', 'schedules.unit']));
    }

    /**
     * Update a group; capacity cannot drop below the number of active enrollments.
     */
    public function update(UpdateAcademyGroupRequest $request, AcademyGroup $group, AuditLogger $audit): JsonResponse
    {
        $group->update($request->validated());

        if ($group->wasChanged()) {
            $audit->logChange('academy_group.updated', $group);
        }

        return response()->json([
            'message' => __('admin_academies.group_updated'),
            'data' => new AcademyGroupResource($group->load('coach')),
        ]);
    }

    /**
     * Delete a group that nothing references.
     *
     * @throws ConflictHttpException when schedules, sessions, enrollments or waitlist entries still reference the group
     */
    public function destroy(AcademyGroup $group, AuditLogger $audit): JsonResponse
    {
        $before = $group->toArray();

        $this->deleteOrConflict($group, __('admin_academies.group_in_use'));

        $audit->log('academy_group.deleted', $group, $before, null);

        return response()->json(['message' => __('admin_academies.group_deleted')]);
    }
}
