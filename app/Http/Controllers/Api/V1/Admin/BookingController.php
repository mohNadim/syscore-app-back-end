<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Bookings\CancelBookingRequest;
use App\Http\Requests\Api\V1\Admin\Bookings\IndexBookingRequest;
use App\Http\Requests\Api\V1\Admin\Bookings\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\User;
use App\Services\Bookings\BookingService;
use App\Services\DisplayTimezone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BookingController extends Controller
{
    /**
     * The relations every booking response carries for staff.
     *
     * @var list<string>
     */
    private const RELATIONS = ['user', 'unit.facility', 'sport', 'discount', 'series', 'createdByStaff'];

    /**
     * List bookings by start time, filtered by local date or date range, status, unit, facility, customer, phone and number.
     */
    public function index(IndexBookingRequest $request, DisplayTimezone $timezone): AnonymousResourceCollection
    {
        $bookings = Booking::query()
            ->with(['user', 'unit.facility', 'sport', 'discount'])
            ->when($request->filled('date'), fn ($query) => $query
                ->where('starts_at', '>=', $timezone->dayStartUtc($request->string('date')->toString()))
                ->where('starts_at', '<=', $timezone->dayEndUtc($request->string('date')->toString())))
            ->when($request->filled('from'), fn ($query) => $query->where('starts_at', '>=', $timezone->dayStartUtc($request->string('from')->toString())))
            ->when($request->filled('to'), fn ($query) => $query->where('starts_at', '<=', $timezone->dayEndUtc($request->string('to')->toString())))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('unit_id'), fn ($query) => $query->where('unit_id', $request->integer('unit_id')))
            ->when($request->filled('facility_id'), fn ($query) => $query->whereHas('unit', fn ($unit) => $unit->where('facility_id', $request->integer('facility_id'))))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('phone'), function ($query) use ($request): void {
                $phone = '%'.addcslashes($request->string('phone')->toString(), '%_\\').'%';

                $query->where(fn ($inner) => $inner
                    ->where('guest_phone', 'like', $phone)
                    ->orWhereHas('user', fn ($user) => $user->where('phone', 'like', $phone)));
            })
            ->when($request->filled('booking_number'), fn ($query) => $query->where('booking_number', 'like', '%'.addcslashes($request->string('booking_number')->toString(), '%_\\').'%'))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15))->withQueryString();

        return BookingResource::collection($bookings);
    }

    /**
     * Show one booking with its customer, unit, sport, discount, series and creator.
     */
    public function show(Booking $booking): BookingResource
    {
        return new BookingResource($booking->load(self::RELATIONS));
    }

    /**
     * Create a front desk booking for a registered user or a guest. Staff are exempt from the advance booking window only.
     *
     * @throws ConflictHttpException when the slot is already taken
     */
    public function store(StoreBookingRequest $request, BookingService $bookings): JsonResponse
    {
        $user = $request->filled('user_id') ? User::query()->findOrFail($request->integer('user_id')) : null;

        $booking = $bookings->create([
            'unit' => $request->unit(),
            'sport_id' => $request->integer('sport_id'),
            'starts_at' => $request->startsAtUtc(),
            'duration_minutes' => $request->integer('duration_minutes'),
            'user' => $user,
            'guest_name' => $request->input('guest_name'),
            'guest_phone' => $request->input('guest_phone'),
            'payment_mode' => $request->string('payment_mode', 'cash_full')->toString(),
            'source' => 'front_desk',
        ], $request->user());

        return response()->json([
            'message' => __('admin_bookings.created'),
            'data' => new BookingResource($booking->load(self::RELATIONS)),
        ], 201);
    }

    /**
     * Cancel a booking that has not ended yet.
     *
     * @throws UnprocessableEntityHttpException when the booking is not cancellable
     */
    public function cancel(CancelBookingRequest $request, Booking $booking, BookingService $bookings): JsonResponse
    {
        $cancelled = $bookings->cancel($booking, null, $request->user(), $request->string('reason')->toString());

        return response()->json([
            'message' => __('admin_bookings.cancelled'),
            'data' => new BookingResource($cancelled->load(self::RELATIONS)),
        ]);
    }

    /**
     * Mark a confirmed booking that has already started as a no-show.
     *
     * @throws UnprocessableEntityHttpException when the booking is not confirmed or has not started
     */
    public function noShow(Booking $booking, BookingService $bookings): JsonResponse
    {
        return response()->json([
            'message' => __('admin_bookings.no_show'),
            'data' => new BookingResource($bookings->markNoShow($booking)->load(self::RELATIONS)),
        ]);
    }
}
