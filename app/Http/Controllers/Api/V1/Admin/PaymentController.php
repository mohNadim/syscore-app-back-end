<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Payments\IndexPaymentRequest;
use App\Http\Requests\Api\V1\Admin\Payments\StorePaymentRequest;
use App\Http\Requests\Api\V1\Admin\Payments\StoreRefundRequest;
use App\Http\Resources\AdminPaymentResource;
use App\Http\Resources\ReceiptResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\DisplayTimezone;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PaymentController extends Controller
{
    /**
     * Relations loaded for every payment response.
     *
     * @var list<string>
     */
    private const WITH = ['user', 'recordedByStaff', 'refunds'];

    /**
     * Relations loaded for a receipt.
     *
     * @var list<string>
     */
    private const RECEIPT_WITH = ['user', 'recordedByStaff', 'refunds', 'booking.unit', 'subscription.enrollment.child', 'subscription.enrollment.group.academy'];

    /**
     * List payments, newest first, filtered by status, method, purpose, target, customer, local date range and receipt number or payer phone.
     */
    public function index(IndexPaymentRequest $request, DisplayTimezone $timezone): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->with(self::WITH)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('method'), fn ($query) => $query->where('method', $request->string('method')->toString()))
            ->when($request->filled('purpose'), fn ($query) => $query->where('purpose', $request->string('purpose')->toString()))
            ->when($request->filled('booking_id'), fn ($query) => $query->where('booking_id', $request->integer('booking_id')))
            ->when($request->filled('subscription_id'), fn ($query) => $query->where('subscription_id', $request->integer('subscription_id')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', $timezone->dayStartUtc($request->string('from')->toString())))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', $timezone->dayEndUtc($request->string('to')->toString())))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.addcslashes($request->string('q')->toString(), '%_\\').'%';

                $query->where(fn ($inner) => $inner->where('receipt_number', 'like', $term)->orWhere('payer_phone', 'like', $term));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))->withQueryString();

        return AdminPaymentResource::collection($payments);
    }

    /**
     * Show one payment with its customer, recording staff member and refunds.
     */
    public function show(Payment $payment): AdminPaymentResource
    {
        return new AdminPaymentResource($payment->load(self::WITH));
    }

    /**
     * Show the receipt of a settled payment, regardless of its customer.
     *
     * @throws UnprocessableEntityHttpException when the payment has no receipt yet
     */
    public function receipt(Payment $payment, PaymentService $payments): ReceiptResource
    {
        $payments->ensureReceiptAvailable($payment);

        return new ReceiptResource($payment->load(self::RECEIPT_WITH));
    }

    /**
     * Record a cash payment taken at the desk for a booking or subscription.
     *
     * @throws UnprocessableEntityHttpException when nothing is payable or the amount is not acceptable
     */
    public function store(StorePaymentRequest $request, PaymentService $payments): JsonResponse
    {
        $target = $request->filled('booking_id')
            ? Booking::query()->findOrFail($request->integer('booking_id'))
            : Subscription::query()->findOrFail($request->integer('subscription_id'));

        $payment = $payments->recordCash(
            $target,
            $request->filled('amount') ? $request->integer('amount') : null,
            $request->user(),
            $request->input('payer_name'),
            $request->input('payer_phone'),
        );

        return response()->json([
            'message' => __('admin_payments.cash_recorded'),
            'data' => new AdminPaymentResource($payment->load(self::WITH)),
        ], 201);
    }

    /**
     * Refund part or all of a payment and return the refreshed payment.
     *
     * @throws UnprocessableEntityHttpException when the payment is not refundable or the amount is too high
     */
    public function refund(StoreRefundRequest $request, Payment $payment, PaymentService $payments): JsonResponse
    {
        $payments->refund($payment, $request->integer('amount'), $request->user(), $request->input('reason'));

        return response()->json([
            'message' => __('admin_payments.refunded'),
            'data' => new AdminPaymentResource($payment->refresh()->load(self::WITH)),
        ], 201);
    }
}
