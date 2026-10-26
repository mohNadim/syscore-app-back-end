<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\IndexAcademyRequest;
use App\Http\Resources\AcademyResource;
use App\Models\Academy;
use App\Services\DisplayTimezone;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AcademyController extends Controller
{
    /**
     * List active academies with their active groups and each group's coach, optionally filtered by sport.
     */
    public function index(IndexAcademyRequest $request): AnonymousResourceCollection
    {
        $academies = Academy::query()
            ->where('is_active', true)
            ->when($request->filled('sport_id'), fn ($query) => $query->where('sport_id', $request->integer('sport_id')))
            ->with([
                'sport',
                'groups' => fn ($query) => $query->where('is_active', true)->orderBy('id'),
                'groups.coach',
            ])
            ->orderBy('id')
            ->get();

        return AcademyResource::collection($academies);
    }

    /**
     * Show one active academy with its active groups, coaches and current schedules (valid_to empty or today onwards).
     */
    public function show(Academy $academy, DisplayTimezone $timezone): AcademyResource
    {
        abort_unless($academy->is_active, 404);

        $today = now($timezone->name())->toDateString();

        $academy->load([
            'sport',
            'groups' => fn ($query) => $query->where('is_active', true)->orderBy('id'),
            'groups.coach',
            'groups.schedules' => fn ($query) => $query
                ->where(fn ($inner) => $inner->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
                ->orderBy('weekday')
                ->orderBy('start_time'),
            'groups.schedules.unit',
        ]);

        return new AcademyResource($academy);
    }
}
