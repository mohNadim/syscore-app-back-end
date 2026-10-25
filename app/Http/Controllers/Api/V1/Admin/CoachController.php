<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\DeletesWithoutBreakingReferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Coaches\IndexCoachRequest;
use App\Http\Requests\Api\V1\Admin\Coaches\StoreCoachRequest;
use App\Http\Requests\Api\V1\Admin\Coaches\UpdateCoachRequest;
use App\Http\Resources\CoachResource;
use App\Models\Coach;
use App\Services\AuditLogger;
use App\Services\PublicImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CoachController extends Controller
{
    use DeletesWithoutBreakingReferences;

    /**
     * List coaches ordered by sort_order then id, optionally filtered by sport and active state.
     */
    public function index(IndexCoachRequest $request): AnonymousResourceCollection
    {
        $coaches = Coach::query()
            ->with('sport')
            ->when($request->filled('sport_id'), fn ($query) => $query->where('sport_id', $request->integer('sport_id')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return CoachResource::collection($coaches);
    }

    /**
     * Create a coach; send multipart/form-data when uploading a photo.
     */
    public function store(StoreCoachRequest $request, AuditLogger $audit, PublicImageStore $images): JsonResponse
    {
        $attributes = $request->safe()->except(['photo', 'remove_photo']);

        if ($request->hasFile('photo')) {
            $attributes['photo_url'] = $images->store($request->file('photo'), 'coaches');
        }

        $coach = Coach::create($attributes);
        $coach->refresh()->load('sport');

        $audit->log('coach.created', $coach, null, $coach->toArray());

        return response()->json([
            'message' => __('admin_coaches.coach_created'),
            'data' => new CoachResource($coach),
        ], 201);
    }

    /**
     * Show one coach.
     */
    public function show(Coach $coach): CoachResource
    {
        return new CoachResource($coach->load('sport'));
    }

    /**
     * Update a coach.
     *
     * PHP does not parse multipart bodies for PUT/PATCH, so when a photo is uploaded the client must send
     * a POST request with the field `_method=PUT` (Laravel method spoofing). JSON requests can use PUT directly.
     * Send `remove_photo=true` to clear the photo; a new `photo` replaces the old one. Replaced or removed
     * files are deleted from the public disk after the update succeeds.
     */
    public function update(UpdateCoachRequest $request, Coach $coach, AuditLogger $audit, PublicImageStore $images): JsonResponse
    {
        $attributes = $request->safe()->except(['photo', 'remove_photo']);
        $oldPhotoUrl = $coach->photo_url;
        $replaceOldPhoto = false;

        if ($request->hasFile('photo')) {
            $attributes['photo_url'] = $images->store($request->file('photo'), 'coaches');
            $replaceOldPhoto = true;
        } elseif ($request->boolean('remove_photo')) {
            $attributes['photo_url'] = null;
            $replaceOldPhoto = true;
        }

        $coach->update($attributes);

        if ($replaceOldPhoto) {
            $images->delete($oldPhotoUrl);
        }

        if ($coach->wasChanged()) {
            $audit->logChange('coach.updated', $coach);
        }

        return response()->json([
            'message' => __('admin_coaches.coach_updated'),
            'data' => new CoachResource($coach->load('sport')),
        ]);
    }

    /**
     * Delete a coach that nothing references and remove the stored photo.
     *
     * @throws ConflictHttpException when academy groups or training sessions still reference the coach
     */
    public function destroy(Coach $coach, AuditLogger $audit, PublicImageStore $images): JsonResponse
    {
        $before = $coach->toArray();

        $this->deleteOrConflict($coach, __('admin_coaches.coach_in_use'));

        $images->delete($before['photo_url'] ?? null);
        $audit->log('coach.deleted', $coach, $before, null);

        return response()->json(['message' => __('admin_coaches.coach_deleted')]);
    }
}
