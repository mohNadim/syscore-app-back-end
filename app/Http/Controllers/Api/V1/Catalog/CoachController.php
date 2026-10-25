<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\IndexCoachRequest;
use App\Http\Resources\CoachResource;
use App\Models\Coach;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CoachController extends Controller
{
    /**
     * List active coaches, optionally filtered by sport.
     */
    public function index(IndexCoachRequest $request): AnonymousResourceCollection
    {
        $coaches = Coach::query()
            ->where('is_active', true)
            ->when($request->filled('sport_id'), fn ($query) => $query->where('sport_id', $request->integer('sport_id')))
            ->with('sport')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return CoachResource::collection($coaches);
    }

    /**
     * Show one active coach; an inactive one responds with 404.
     */
    public function show(Coach $coach): CoachResource
    {
        abort_unless($coach->is_active, 404);

        return new CoachResource($coach->load('sport'));
    }
}
