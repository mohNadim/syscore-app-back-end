<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Bookings\CancelBookingRequest;
use App\Http\Requests\Api\V1\Bookings\IndexBookingRequest;
use App\Http\Requests\Api\V1\Bookings\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\Bookings\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BookingController extends Controller
{
    /**
     * List the authenticated user's bookings: upcoming ones soonest first, past ones latest first, all newest first.
     */
    public function index(IndexBookingRequest $request): AnonymousResourceCollection
    {
        $bookings = $request->user()->bookings()
            ->with(['unit.facility', 'sport', 'discount'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->string('scope')->toString() === 'upcoming', fn ($query) => $query->where('ends_at', '>=', now())->orderBy('starts_at')->orderBy('id'))
            ->when($request->string('scope')->toString() === 'past', fn ($query) => $query->where('ends_at', '<', now())->orderByDesc('starts_at')->orderByDesc('id'))
            ->when(! $request->filled('scope'), fn ($query) => $query->orderByDesc('starts_at')->orderByDesc('id'))
            ->paginate($request->integer('per_page', 15))->withQueryString();

        return BookingResource::collection($bookings);
    }

    /**
     * Book a unit for the authenticated user; the deposit (or the full price) must then be paid online.
     *
     * @throws ConflictHttpException when the slot was taken meanwhile
     */
    public function store(StoreBookingRequest $request, BookingService $bookings): JsonResponse
    {
        $booking = $bookings->create([
            'unit' => $request->unit(),
            'sport_id' => $request->integer('sport_id'),
            'starts_at' => $request->startsAtUtc(),
            'duration_minutes' => $request->integer('duration_minutes'),
            'user' => $request->user(),
            'payment_mode' => $request->string('payment_mode')->toString(),
            'source' => 'app',
        ]);

        return response()->json([
            'message' => __('bookings.created'),
            'data' => new BookingResource($booking->load(['unit.facility', 'sport', 'discount'])),
        ], 201);
    }

    /**
     * Show one of the user's bookings.
     */
    public function show(Booking $booking): BookingResource
    {
        Gate::authorize('view', $booking);

        return new BookingResource($booking->load(['unit.facility', 'sport', 'discount']));
    }

    /**
     * Cancel one of the user's bookings.
     *
     * @throws UnprocessableEntityHttpException when the booking is not cancellable or the deadline passed
     */
    public function cancel(CancelBookingRequest $request, Booking $booking, BookingService $bookings): JsonResponse
    {
        Gate::authorize('cancel', $booking);

        $cancelled = $bookings->cancel($booking, $request->user(), null, $request->input('reason'));

        return response()->json([
            'message' => __('bookings.cancelled'),
            'data' => new BookingResource($cancelled->load(['unit.facility', 'sport', 'discount'])),
        ]);
    }
}
