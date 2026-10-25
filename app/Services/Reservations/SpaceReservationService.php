<?php

namespace App\Services\Reservations;

use App\Models\Closure;
use App\Models\Space;
use App\Models\SpaceReservation;
use App\Models\TrainingSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Guards space availability. MySQL has no exclusion constraint, so every write path must call
 * lockSpaces() inside a DB transaction BEFORE conflicts(); the row locks serialize concurrent writers.
 */
class SpaceReservationService
{
    /**
     * Lock the given spaces (SELECT ... FOR UPDATE, ordered by id to avoid deadlocks); call inside a transaction.
     *
     * @param  list<int>  $spaceIds
     */
    public function lockSpaces(array $spaceIds): void
    {
        if ($spaceIds === []) {
            return;
        }

        Space::query()->whereKey($spaceIds)->orderBy('id')->lockForUpdate()->get();
    }

    /**
     * Get the active reservations on the spaces that strictly overlap the interval (touching edges do not conflict).
     *
     * @param  list<int>  $spaceIds
     * @return Collection<int, SpaceReservation>
     */
    public function conflicts(array $spaceIds, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $ignoreSessionId = null): Collection
    {
        return SpaceReservation::query()
            ->whereIn('space_id', $spaceIds)
            ->where('is_active', true)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($ignoreSessionId !== null, fn ($query) => $query->where(
                fn ($inner) => $inner->whereNull('session_id')->orWhere('session_id', '!=', $ignoreSessionId)
            ))
            ->get();
    }

    /**
     * Determine whether a closure of the facility or of one of the spaces overlaps the interval.
     *
     * @param  list<int>  $spaceIds
     */
    public function closed(int $facilityId, array $spaceIds, CarbonInterface $startsAt, CarbonInterface $endsAt): bool
    {
        return Closure::query()
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->where(fn ($query) => $query
                ->where('facility_id', $facilityId)
                ->orWhereIn('space_id', $spaceIds))
            ->exists();
    }

    /**
     * Insert one active reservation per space for the session's interval.
     *
     * @param  list<int>  $spaceIds
     */
    public function reserveForSession(TrainingSession $session, array $spaceIds): void
    {
        foreach ($spaceIds as $spaceId) {
            SpaceReservation::create([
                'space_id' => $spaceId,
                'starts_at' => $session->starts_at,
                'ends_at' => $session->ends_at,
                'session_id' => $session->id,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Deactivate every reservation of the session.
     */
    public function releaseForSession(TrainingSession $session): void
    {
        SpaceReservation::query()->where('session_id', $session->id)->update(['is_active' => false]);
    }
}
