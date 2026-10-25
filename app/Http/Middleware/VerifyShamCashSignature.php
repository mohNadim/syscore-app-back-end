<?php

namespace App\Http\Middleware;

use App\Services\Payments\ShamCash\ShamCashGateway;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class VerifyShamCashSignature
{
    public function __construct(protected ShamCashGateway $gateway) {}

    /**
     * Allow only requests whose raw body is signed with the Sham Cash shared secret, before any validation runs.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws UnauthorizedHttpException when the signature is missing or wrong
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->gateway->verifySignature($request->getContent(), $request->header('X-ShamCash-Signature'))) {
            throw new UnauthorizedHttpException('', __('payments.invalid_signature'));
        }

        return $next($request);
    }
}
