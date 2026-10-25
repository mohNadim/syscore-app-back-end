<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\DeletesWithoutBreakingReferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\BookableUnits\StoreBookableUnitRequest;
use App\Http\Requests\Api\V1\Admin\BookableUnits\SyncUnitSportsRequest;
use App\Http\Requests\Api\V1\Admin\BookableUnits\UpdateBookableUnitRequest;
use App\Http\Resources\BookableUnitResource;
use App\Models\BookableUnit;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BookableUnitController extends Controller
{
    use DeletesWithoutBreakingReferences;

    /**
     * List bookable units with their spaces and sports, optionally filtered by facility and active state.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $units = BookableUnit::query()
            ->with(['spaces', 'sports'])
            ->when($request->filled('facility_id'), fn ($query) => $query->where('facility_id', $request->integer('facility_id')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return BookableUnitResource::collection($units);
    }

    /**
     * Create a bookable unit and link its spaces and sports.
     */
    public function store(StoreBookableUnitRequest $request, AuditLogger $audit): JsonResponse
    {
        $unit = DB::transaction(function () use ($request): BookableUnit {
            $unit = BookableUnit::create($request->safe()->except(['space_ids', 'sport_ids']));
            $unit->spaces()->sync($request->validated('space_ids'));
            $unit->sports()->sync($request->validated('sport_ids', []));

            return $unit->refresh();
        });

        $unit->load(['spaces', 'sports']);

        $audit->log('bookable_unit.created', $unit, null, [
            ...$unit->withoutRelations()->toArray(),
            'space_ids' => $this->relatedIds($unit, 'spaces'),
            'sport_ids' => $this->relatedIds($unit, 'sports'),
        ]);

        return response()->json([
            'message' => __('admin_facilities.unit_created'),
            'data' => new BookableUnitResource($unit),
        ], 201);
    }

    /**
     * Show one bookable unit with its spaces and sports.
     */
    public function show(BookableUnit $bookableUnit): BookableUnitResource
    {
        return new BookableUnitResource($bookableUnit->load(['spaces', 'sports']));
    }

    /**
     * Update a bookable unit and optionally replace its spaces and sports.
     */
    public function update(UpdateBookableUnitRequest $request, BookableUnit $bookableUnit, AuditLogger $audit): JsonResponse
    {
        $bookableUnit->load(['spaces', 'sports']);
        $spacesBefore = $this->relatedIds($bookableUnit, 'spaces');
        $sportsBefore = $this->relatedIds($bookableUnit, 'sports');

        DB::transaction(function () use ($request, $bookableUnit): void {
            $bookableUnit->update($request->safe()->except(['space_ids', 'sport_ids']));

            if ($request->has('space_ids')) {
                $bookableUnit->spaces()->sync($request->validated('space_ids'));
            }

            if ($request->has('sport_ids')) {
                $bookableUnit->sports()->sync($request->validated('sport_ids'));
            }
        });

        if ($bookableUnit->wasChanged()) {
            $audit->logChange('bookable_unit.updated', $bookableUnit);
        }

        $bookableUnit->load(['spaces', 'sports']);
        $spacesAfter = $this->relatedIds($bookableUnit, 'spaces');
        $sportsAfter = $this->relatedIds($bookableUnit, 'sports');

        if ($spacesBefore !== $spacesAfter) {
            $audit->log('bookable_unit.spaces_updated', $bookableUnit, ['space_ids' => $spacesBefore], ['space_ids' => $spacesAfter]);
        }

        if ($sportsBefore !== $sportsAfter) {
            $audit->log('bookable_unit.sports_updated', $bookableUnit, ['sport_ids' => $sportsBefore], ['sport_ids' => $sportsAfter]);
        }

        return response()->json([
            'message' => __('admin_facilities.unit_updated'),
            'data' => new BookableUnitResource($bookableUnit),
        ]);
    }

    /**
     * Replace the sports a bookable unit is used for; an empty list unlinks all sports.
     */
    public function syncSports(SyncUnitSportsRequest $request, BookableUnit $bookableUnit, AuditLogger $audit): JsonResponse
    {
        $bookableUnit->load('sports');
        $before = $this->relatedIds($bookableUnit, 'sports');

        $bookableUnit->sports()->sync($request->validated('sport_ids'));
        $bookableUnit->load(['spaces', 'sports']);
        $after = $this->relatedIds($bookableUnit, 'sports');

        if ($before !== $after) {
            $audit->log('bookable_unit.sports_updated', $bookableUnit, ['sport_ids' => $before], ['sport_ids' => $after]);
        }

        return response()->json([
            'message' => __('admin_facilities.unit_sports_updated'),
            'data' => new BookableUnitResource($bookableUnit),
        ]);
    }

    /**
     * Delete a bookable unit that nothing references, detaching its spaces and sports.
     *
     * @throws ConflictHttpException when bookings, price rules, schedules, sessions or series still reference the unit
     */
    public function destroy(BookableUnit $bookableUnit, AuditLogger $audit): JsonResponse
    {
        $bookableUnit->load(['spaces', 'sports']);
        $before = [
            ...$bookableUnit->withoutRelations()->toArray(),
            'space_ids' => $this->relatedIds($bookableUnit, 'spaces'),
            'sport_ids' => $this->relatedIds($bookableUnit, 'sports'),
        ];

        $this->deleteOrConflict($bookableUnit, __('admin_facilities.unit_in_use'), function () use ($bookableUnit): void {
            $bookableUnit->spaces()->detach();
            $bookableUnit->sports()->detach();
        });

        $audit->log('bookable_unit.deleted', $bookableUnit, $before, null);

        return response()->json(['message' => __('admin_facilities.unit_deleted')]);
    }

    /**
     * Get the sorted ids of a loaded relation of the unit.
     *
     * @param  'spaces'|'sports'  $relation
     * @return list<int>
     */
    private function relatedIds(BookableUnit $unit, string $relation): array
    {
        return $unit->{$relation}->pluck('id')->sort()->values()->all();
    }
}
