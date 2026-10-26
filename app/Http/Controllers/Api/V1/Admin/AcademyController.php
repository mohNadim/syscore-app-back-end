<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\DeletesWithoutBreakingReferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Academies\IndexAcademyRequest;
use App\Http\Requests\Api\V1\Admin\Academies\StoreAcademyRequest;
use App\Http\Requests\Api\V1\Admin\Academies\UpdateAcademyRequest;
use App\Http\Resources\AcademyResource;
use App\Models\Academy;
use App\Services\AuditLogger;
use App\Services\PublicImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AcademyController extends Controller
{
    use DeletesWithoutBreakingReferences;

    /**
     * List academies with their group count, optionally filtered by sport and active state.
     */
    public function index(IndexAcademyRequest $request): AnonymousResourceCollection
    {
        $academies = Academy::query()
            ->with('sport')
            ->withCount('groups')
            ->when($request->filled('sport_id'), fn ($query) => $query->where('sport_id', $request->integer('sport_id')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('id')
            ->get();

        return AcademyResource::collection($academies);
    }

    /**
     * Create an academy; send multipart/form-data when uploading a cover image.
     */
    public function store(StoreAcademyRequest $request, AuditLogger $audit, PublicImageStore $images): JsonResponse
    {
        $attributes = $request->safe()->except(['cover', 'remove_cover']);

        if ($request->hasFile('cover')) {
            $attributes['cover_url'] = $images->store($request->file('cover'), 'academies');
        }

        $academy = Academy::create($attributes);
        $academy->refresh()->load('sport')->loadCount('groups');

        $audit->log('academy.created', $academy, null, $academy->toArray());

        return response()->json([
            'message' => __('admin_academies.academy_created'),
            'data' => new AcademyResource($academy),
        ], 201);
    }

    /**
     * Show one academy with its sport and groups.
     */
    public function show(Academy $academy): AcademyResource
    {
        return new AcademyResource($academy->load(['sport', 'groups.coach'])->loadCount('groups'));
    }

    /**
     * Update an academy.
     *
     * PHP does not parse multipart bodies for PUT/PATCH, so when a cover is uploaded the client must send
     * a POST request with the field `_method=PUT` (Laravel method spoofing). JSON requests can use PUT directly.
     * Send `remove_cover=true` to clear the cover; a new `cover` replaces the old one. Replaced or removed
     * files are deleted from the public disk after the update succeeds.
     */
    public function update(UpdateAcademyRequest $request, Academy $academy, AuditLogger $audit, PublicImageStore $images): JsonResponse
    {
        $attributes = $request->safe()->except(['cover', 'remove_cover']);
        $oldCoverUrl = $academy->cover_url;
        $replaceOldCover = false;

        if ($request->hasFile('cover')) {
            $attributes['cover_url'] = $images->store($request->file('cover'), 'academies');
            $replaceOldCover = true;
        } elseif ($request->boolean('remove_cover')) {
            $attributes['cover_url'] = null;
            $replaceOldCover = true;
        }

        $academy->update($attributes);

        if ($replaceOldCover) {
            $images->delete($oldCoverUrl);
        }

        if ($academy->wasChanged()) {
            $audit->logChange('academy.updated', $academy);
        }

        return response()->json([
            'message' => __('admin_academies.academy_updated'),
            'data' => new AcademyResource($academy->load('sport')->loadCount('groups')),
        ]);
    }

    /**
     * Delete an academy that nothing references and remove the stored cover.
     *
     * @throws ConflictHttpException when groups or discounts still reference the academy
     */
    public function destroy(Academy $academy, AuditLogger $audit, PublicImageStore $images): JsonResponse
    {
        $before = $academy->toArray();

        $this->deleteOrConflict($academy, __('admin_academies.academy_in_use'));

        $images->delete($before['cover_url'] ?? null);
        $audit->log('academy.deleted', $academy, $before, null);

        return response()->json(['message' => __('admin_academies.academy_deleted')]);
    }
}
