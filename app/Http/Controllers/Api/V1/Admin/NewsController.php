<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\News\IndexNewsRequest;
use App\Http\Requests\Api\V1\Admin\News\StoreNewsRequest;
use App\Http\Requests\Api\V1\Admin\News\UpdateNewsRequest;
use App\Http\Resources\AdminNewsResource;
use App\Models\News;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NewsController extends Controller
{
    /**
     * List news articles, newest first, paginated; optionally filtered by published state.
     */
    public function index(IndexNewsRequest $request): AnonymousResourceCollection
    {
        $news = News::query()
            ->when($request->filled('is_published'), fn ($query) => $query->where('is_published', $request->boolean('is_published')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))->withQueryString();

        return AdminNewsResource::collection($news);
    }

    /**
     * Create a news article; the authenticated staff member is recorded as its creator.
     */
    public function store(StoreNewsRequest $request, AuditLogger $audit): JsonResponse
    {
        $news = News::create([
            ...$request->newsAttributes(),
            'created_by_staff_id' => $request->user()->getKey(),
        ])->refresh();

        $audit->log('news.created', $news, null, $news->toArray());

        return response()->json([
            'message' => __('admin_news.news_created'),
            'data' => new AdminNewsResource($news),
        ], 201);
    }

    /**
     * Show one news article, including unpublished ones.
     */
    public function show(News $news): AdminNewsResource
    {
        return new AdminNewsResource($news);
    }

    /**
     * Update a news article.
     */
    public function update(UpdateNewsRequest $request, News $news, AuditLogger $audit): JsonResponse
    {
        $news->update($request->newsAttributes());

        if ($news->wasChanged()) {
            $audit->logChange('news.updated', $news);
        }

        return response()->json([
            'message' => __('admin_news.news_updated'),
            'data' => new AdminNewsResource($news),
        ]);
    }

    /**
     * Delete a news article.
     */
    public function destroy(News $news, AuditLogger $audit): JsonResponse
    {
        $before = $news->toArray();

        $news->delete();
        $audit->log('news.deleted', $news, $before, null);

        return response()->json(['message' => __('admin_news.news_deleted')]);
    }
}
