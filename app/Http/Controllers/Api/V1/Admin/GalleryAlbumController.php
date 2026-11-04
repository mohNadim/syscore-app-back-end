<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Gallery\IndexGalleryAlbumRequest;
use App\Http\Requests\Api\V1\Admin\Gallery\StoreGalleryAlbumRequest;
use App\Http\Requests\Api\V1\Admin\Gallery\UpdateGalleryAlbumRequest;
use App\Http\Resources\GalleryAlbumResource;
use App\Models\GalleryAlbum;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class GalleryAlbumController extends Controller
{
    /**
     * List gallery albums ordered by sort_order then id, with photo counts, paginated.
     */
    public function index(IndexGalleryAlbumRequest $request): AnonymousResourceCollection
    {
        $albums = GalleryAlbum::query()
            ->withCount('photos')
            ->with('coverPhoto')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15))->withQueryString();

        return GalleryAlbumResource::collection($albums);
    }

    /**
     * Create a gallery album.
     */
    public function store(StoreGalleryAlbumRequest $request, AuditLogger $audit): JsonResponse
    {
        $album = GalleryAlbum::create($request->validated())->refresh();

        $audit->log('gallery_album.created', $album, null, $album->toArray());

        return response()->json([
            'message' => __('admin_gallery.album_created'),
            'data' => new GalleryAlbumResource($this->withPhotos($album)),
        ], 201);
    }

    /**
     * Show one gallery album with its photos.
     */
    public function show(GalleryAlbum $album): GalleryAlbumResource
    {
        return new GalleryAlbumResource($this->withPhotos($album));
    }

    /**
     * Update a gallery album.
     */
    public function update(UpdateGalleryAlbumRequest $request, GalleryAlbum $album, AuditLogger $audit): JsonResponse
    {
        $album->update($request->validated());

        if ($album->wasChanged()) {
            $audit->logChange('gallery_album.updated', $album);
        }

        return response()->json([
            'message' => __('admin_gallery.album_updated'),
            'data' => new GalleryAlbumResource($this->withPhotos($album)),
        ]);
    }

    /**
     * Delete a gallery album together with its photos; the photo foreign key is restrictive, so photos are removed first.
     */
    public function destroy(GalleryAlbum $album, AuditLogger $audit): JsonResponse
    {
        $before = $album->toArray();

        DB::transaction(function () use ($album, $audit, $before): void {
            foreach ($album->photos()->get() as $photo) {
                $audit->log('gallery_photo.deleted', $photo, $photo->toArray(), null);
            }

            $album->photos()->delete();
            $album->delete();
            $audit->log('gallery_album.deleted', $album, $before, null);
        });

        return response()->json(['message' => __('admin_gallery.album_deleted')]);
    }

    /**
     * Load the album's photos in display order.
     */
    private function withPhotos(GalleryAlbum $album): GalleryAlbum
    {
        return $album->load(['photos' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->loadCount('photos');
    }
}
