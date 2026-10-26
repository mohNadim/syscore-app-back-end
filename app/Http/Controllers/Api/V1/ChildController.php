<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Children\StoreChildRequest;
use App\Http\Requests\Api\V1\Children\UpdateChildRequest;
use App\Http\Resources\ChildResource;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ChildController extends Controller
{
    /**
     * List the authenticated user's active children.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $children = $request->user()->children()
            ->where('is_active', true)
            ->orderBy('birth_date')
            ->get();

        return ChildResource::collection($children);
    }

    /**
     * Register a new child under the authenticated user.
     */
    public function store(StoreChildRequest $request): JsonResponse
    {
        $child = $request->user()->children()->create([...$request->validated(), 'is_active' => true]);

        return response()->json([
            'message' => __('children.created'),
            'data' => new ChildResource($child->refresh()),
        ], 201);
    }

    /**
     * Show one of the user's children.
     */
    public function show(Child $child): ChildResource
    {
        Gate::authorize('view', $child);

        return new ChildResource($child);
    }

    /**
     * Update one of the user's children.
     */
    public function update(UpdateChildRequest $request, Child $child): JsonResponse
    {
        Gate::authorize('update', $child);

        $child->update($request->validated());

        return response()->json([
            'message' => __('children.updated'),
            'data' => new ChildResource($child),
        ]);
    }

    /**
     * Remove a child: delete it when it has no academy history, otherwise archive it to keep the records.
     *
     * @throws ConflictHttpException when the child is still enrolled in a group
     */
    public function destroy(Child $child): JsonResponse
    {
        Gate::authorize('delete', $child);

        if ($child->enrollments()->where('status', 'active')->exists()) {
            throw new ConflictHttpException(__('children.has_active_enrollment'));
        }

        if ($child->hasHistory()) {
            $child->update(['is_active' => false]);

            return response()->json(['message' => __('children.archived')]);
        }

        $child->delete();

        return response()->json(['message' => __('children.deleted')]);
    }
}
