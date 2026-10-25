<?php

namespace App\Http\Middleware;

use App\Models\Staff;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Allow only active staff holding at least one of the given permission codes.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next, string ...$codes): Response
    {
        $staff = $request->user();

        if (! $staff instanceof Staff || ! $staff->is_active) {
            throw new AuthorizationException;
        }

        foreach ($codes as $code) {
            if ($staff->hasPermission($code)) {
                return $next($request);
            }
        }

        throw new AuthorizationException;
    }
}
