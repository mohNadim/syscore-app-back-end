<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\LoginRequest;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Bcrypt hash (cost 12) of a random string, checked when the username is unknown
     * so response time does not reveal which usernames exist.
     */
    private const DUMMY_PASSWORD_HASH = '$2y$12$nwhmkpBmZTSSlUzWnb9lxumoWJniqhpis7o0TE4wMvCD3xARz8E.K';

    /**
     * Log a staff member in with username and password and issue an API token.
     *
     * @throws ValidationException when the credentials do not match
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $staff = Staff::query()->where('username', $request->validated('username'))->first();

        $passwordMatches = Hash::check(
            $request->validated('password'),
            $staff?->password_hash ?? self::DUMMY_PASSWORD_HASH,
        );

        if ($staff === null || ! $passwordMatches) {
            throw ValidationException::withMessages(['username' => __('admin_auth.failed')]);
        }

        if (! $staff->is_active) {
            abort(403, __('admin_auth.account_disabled'));
        }

        $staff->update(['last_login_at' => now()]);

        $token = $staff->createToken($request->validated('device_name') ?? 'admin')->plainTextToken;

        return response()->json([
            'message' => __('admin_auth.logged_in'),
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'staff' => new StaffResource($staff->load('role.permissions')),
            ],
        ]);
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('admin_auth.logged_out')]);
    }

    /**
     * Show the authenticated staff member with their role and permissions.
     */
    public function me(Request $request): StaffResource
    {
        return new StaffResource($request->user()->load('role.permissions'));
    }
}
