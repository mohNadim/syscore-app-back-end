<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payments\ShamCashWebhookRequest;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;

class ShamCashWebhookController extends Controller
{
    /**
     * Receive a payment result from Sham Cash; the body is signed with the shared secret (checked by the `sham-cash.signature` middleware).
     */
    public function __invoke(ShamCashWebhookRequest $request, PaymentService $payments): JsonResponse
    {
        $payment = $payments->handleShamCashWebhook($request->validated());

        return response()->json(['data' => ['id' => $payment->id, 'status' => $payment->status]]);
    }
}
