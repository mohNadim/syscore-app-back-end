<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RequestOtpRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthOtpController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * Send a one-time login code to the phone number.
     */
    public function requestCode(RequestOtpRequest $request): JsonResponse
    {
        $timing = $this->otp->issue($request->validated('phone'));

        return response()->json([
            'message' => __('otp.sent'),
            'data' => $timing,
        ], 202);
    }

    /**
     * Verify the code and issue an API token, registering the user on first login.
     */
    public function verifyCode(VerifyOtpRequest $request): JsonResponse
    {
        ['user' => $user, 'is_new' => $isNewUser] = $this->otp->verify(
            $request->validated('phone'),
            (string) $request->validated('code'),
        );

        $token = $user->createToken($request->validated('device_name') ?? 'mobile')->plainTextToken;

        return response()->json([
            'message' => __('otp.logged_in'),
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'is_new_user' => $isNewUser,
                'user' => new UserResource($user),
            ],
        ]);
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('otp.logged_out')]);
    }
}
