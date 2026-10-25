<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\IndexNewsRequest;
use App\Http\Resources\NewsResource;
use App\Models\News;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NewsController extends Controller
{
    /**
     * List published news (publish date reached), newest first, paginated.
     */
    public function index(IndexNewsRequest $request): AnonymousResourceCollection
    {
        $news = $this->visible()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return NewsResource::collection($news);
    }

    /**
     * Show one published article; an unpublished or scheduled one responds with 404.
     */
    public function show(int $news): NewsResource
    {
        return new NewsResource($this->visible()->findOrFail($news));
    }

    /**
     * Limit the query to articles that are published and whose publish date has been reached.
     *
     * @return Builder<News>
     */
    private function visible(): Builder
    {
        return News::query()
            ->where('is_published', true)
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
