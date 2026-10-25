<?php

namespace App\Http\Middleware;

use App\Models\Staff;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaff
{
    /**
     * Allow only active staff members.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $staff = $request->user();

        if (! $staff instanceof Staff || ! $staff->is_active) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
