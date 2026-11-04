<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\SeriesUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BookingSeries\CancelBookingSeriesRequest;
use App\Http\Requests\Api\V1\BookingSeries\IndexBookingSeriesRequest;
use App\Http\Requests\Api\V1\BookingSeries\PreviewBookingSeriesRequest;
use App\Http\Requests\Api\V1\BookingSeries\StoreBookingSeriesRequest;
use App\Http\Resources\BookingSeriesResource;
use App\Models\BookingSeries;
use App\Services\Bookings\BookingSeriesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BookingSeriesController extends Controller
{
    /**
     * List the authenticated user's recurring bookings, newest first.
     */
    public function index(IndexBookingSeriesRequest $request): AnonymousResourceCollection
    {
        $series = BookingSeries::query()
            ->where('user_id', $request->user()->id)
            ->with(['unit.facility', 'sport'])
            ->withCount('bookings')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))->withQueryString();

        return BookingSeriesResource::collection($series);
    }

    /**
     * Preview the weekly dates of a recurring booking with their availability and prices. Writes nothing.
     */
    public function preview(PreviewBookingSeriesRequest $request, BookingSeriesService $series): JsonResponse
    {
        return response()->json(['data' => $series->preview($request->seriesData(), $request->user())]);
    }

    /**
     * Create a recurring booking: all weekly dates, or only the available ones when the customer agreed to skip the rest.
     *
     * @throws SeriesUnavailableException when some dates are unavailable and skip_unavailable is false
     * @throws ConflictHttpException when no date is available
     */
    public function store(StoreBookingSeriesRequest $request, BookingSeriesService $series): JsonResponse
    {
        $result = $series->create([
            ...$request->seriesData(),
            'payment_mode' => $request->string('payment_mode')->toString(),
            'skip_unavailable' => $request->boolean('skip_unavailable'),
        ], $request->user());

        return response()->json([
            'message' => __('booking_series.created'),
            'data' => new BookingSeriesResource($result['series']->load(['unit.facility', 'sport', 'bookings' => fn ($query) => $query->orderBy('starts_at')])),
            'skipped' => $result['skipped'],
        ], 201);
    }

    /**
     * Show one of the user's recurring bookings with its bookings ordered by start.
     */
    public function show(BookingSeries $bookingSeries): BookingSeriesResource
    {
        Gate::authorize('view', $bookingSeries);

        return new BookingSeriesResource($bookingSeries->load(['unit.facility', 'sport', 'bookings' => fn ($query) => $query->orderBy('starts_at')]));
    }

    /**
     * Cancel the future bookings of a series that can still be cancelled; late ones are kept.
     *
     * @throws UnprocessableEntityHttpException when the series is not active
     */
    public function cancel(CancelBookingSeriesRequest $request, BookingSeries $bookingSeries, BookingSeriesService $series): JsonResponse
    {
        Gate::authorize('cancel', $bookingSeries);

        return response()->json([
            'message' => __('booking_series.cancelled'),
            'data' => $series->cancel($bookingSeries, $request->user(), $request->input('reason')),
        ]);
    }
}
