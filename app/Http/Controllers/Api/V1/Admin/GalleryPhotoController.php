<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Gallery\StoreGalleryPhotoRequest;
use App\Http\Requests\Api\V1\Admin\Gallery\UpdateGalleryPhotoRequest;
use App\Http\Resources\GalleryPhotoResource;
use App\Models\GalleryAlbum;
use App\Models\GalleryPhoto;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;

class GalleryPhotoController extends Controller
{
    /**
     * Add a photo to an album.
     */
    public function store(StoreGalleryPhotoRequest $request, GalleryAlbum $album, AuditLogger $audit): JsonResponse
    {
        $photo = $album->photos()->create($request->validated())->refresh();

        $audit->log('gallery_photo.created', $photo, null, $photo->toArray());

        return response()->json([
            'message' => __('admin_gallery.photo_created'),
            'data' => new GalleryPhotoResource($photo),
        ], 201);
    }

    /**
     * Update a photo's image URL, captions or sort order.
     */
    public function update(UpdateGalleryPhotoRequest $request, GalleryPhoto $photo, AuditLogger $audit): JsonResponse
    {
        $photo->update($request->validated());

        if ($photo->wasChanged()) {
            $audit->logChange('gallery_photo.updated', $photo);
        }

        return response()->json([
            'message' => __('admin_gallery.photo_updated'),
            'data' => new GalleryPhotoResource($photo),
        ]);
    }

    /**
     * Delete a photo.
     */
    public function destroy(GalleryPhoto $photo, AuditLogger $audit): JsonResponse
    {
        $before = $photo->toArray();

        $photo->delete();
        $audit->log('gallery_photo.deleted', $photo, $before, null);

        return response()->json(['message' => __('admin_gallery.photo_deleted')]);
    }
}
