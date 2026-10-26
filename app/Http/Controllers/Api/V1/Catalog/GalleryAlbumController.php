<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryAlbumResource;
use App\Models\GalleryAlbum;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GalleryAlbumController extends Controller
{
    /**
     * List published albums with their photo count and cover image.
     */
    public function index(): AnonymousResourceCollection
    {
        $albums = GalleryAlbum::query()
            ->where('is_published', true)
            ->withCount('photos')
            ->with('coverPhoto')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return GalleryAlbumResource::collection($albums);
    }

    /**
     * Show one published album with its photos; an unpublished one responds with 404.
     */
    public function show(GalleryAlbum $album): GalleryAlbumResource
    {
        abort_unless($album->is_published, 404);

        $album->loadCount('photos')->load(['photos' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')]);

        return new GalleryAlbumResource($album);
    }
}
