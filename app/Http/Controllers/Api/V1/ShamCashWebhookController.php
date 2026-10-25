<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payments\ShamCashWebhookRequest;
use App\Services\Payments\PaymentService;
use App\Services\Payments\ShamCash\ShamCashGateway;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class ShamCashWebhookController extends Controller
{
    /**
     * Receive a payment result from Sham Cash; the body must be signed with the shared secret.
     *
     * @throws UnauthorizedHttpException when the signature is missing or wrong
     */
    public function __invoke(ShamCashWebhookRequest $request, ShamCashGateway $gateway, PaymentService $payments): JsonResponse
    {
        if (! $gateway->verifySignature($request->getContent(), $request->header('X-ShamCash-Signature'))) {
            throw new UnauthorizedHttpException('', __('payments.invalid_signature'));
        }

        $payment = $payments->handleShamCashWebhook($request->validated());

        return response()->json(['data' => ['id' => $payment->id, 'status' => $payment->status]]);
    }
}
