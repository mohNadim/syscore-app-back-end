<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payments\IndexPaymentRequest;
use App\Http\Requests\Api\V1\Payments\InitiateShamCashPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ReceiptResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PaymentController extends Controller
{
    /**
     * Relations loaded for a receipt.
     *
     * @var list<string>
     */
    private const RECEIPT_WITH = ['user', 'recordedByStaff', 'refunds', 'booking.unit', 'subscription.enrollment.child', 'subscription.enrollment.group.academy'];

    /**
     * List the authenticated user's payments, newest first.
     */
    public function index(IndexPaymentRequest $request): AnonymousResourceCollection
    {
        $payments = $request->user()->payments()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))->withQueryString();

        return PaymentResource::collection($payments);
    }

    /**
     * Show one of the user's payments.
     */
    public function show(Payment $payment): PaymentResource
    {
        abort_unless($payment->user_id === request()->user()->id, 404);

        return new PaymentResource($payment);
    }

    /**
     * Show the receipt of one of the user's settled payments.
     *
     * @throws UnprocessableEntityHttpException when the payment has no receipt yet
     */
    public function receipt(Payment $payment, PaymentService $payments): ReceiptResource
    {
        abort_unless($payment->user_id === request()->user()->id, 404);

        $payments->ensureReceiptAvailable($payment);

        return new ReceiptResource($payment->load(self::RECEIPT_WITH));
    }

    /**
     * Start a Sham Cash payment for one of the user's bookings or subscriptions.
     *
     * @throws UnprocessableEntityHttpException when nothing is payable on the target
     */
    public function initiateShamCash(InitiateShamCashPaymentRequest $request, PaymentService $payments): JsonResponse
    {
        $user = $request->user();

        $target = $request->filled('booking_id')
            ? Booking::query()->where('user_id', $user->id)->findOrFail($request->integer('booking_id'))
            : Subscription::query()
                ->whereHas('enrollment.child', fn ($child) => $child->where('user_id', $user->id))
                ->findOrFail($request->integer('subscription_id'));

        $payment = $payments->initiateShamCash($user, $target);

        return response()->json([
            'message' => __('payments.initiated'),
            'data' => [
                ...(new PaymentResource($payment))->resolve($request),
                'checkout_url' => $payment->provider_payload['checkout_url'] ?? null,
            ],
        ], 201);
    }
}
