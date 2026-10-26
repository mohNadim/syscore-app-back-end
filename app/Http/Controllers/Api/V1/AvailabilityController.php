<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Availability\ShowAvailabilityRequest;
use App\Services\Reservations\AvailabilityService;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    /**
     * Show the slot grid of every active unit of a facility for one day. Public.
     */
    public function show(ShowAvailabilityRequest $request, AvailabilityService $availability): JsonResponse
    {
        return response()->json(['data' => $availability->forFacilityDay(
            $request->facility(),
            $request->localDate(),
            $request->filled('sport_id') ? $request->integer('sport_id') : null,
            $request->durationMinutes(),
        )]);
    }
}
