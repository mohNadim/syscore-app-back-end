<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Devices\DestroyDeviceRequest;
use App\Http\Requests\Api\V1\Devices\StoreDeviceRequest;
use App\Http\Resources\UserDeviceResource;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /**
     * Register the device's push token for the authenticated user.
     * A token already known (for example after switching accounts on the same phone) moves to this user.
     */
    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $device = UserDevice::query()->updateOrCreate(
            ['push_token' => $request->validated('push_token')],
            [
                'user_id' => $request->user()->id,
                'platform' => $request->validated('platform'),
                'app_version' => $request->validated('app_version'),
                'last_seen_at' => now(),
            ],
        );

        return response()->json([
            'message' => __('devices.registered'),
            'data' => new UserDeviceResource($device->refresh()),
        ], $device->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Remove one of the authenticated user's push tokens, typically before logging out.
     */
    public function destroy(DestroyDeviceRequest $request): JsonResponse
    {
        $request->user()->devices()
            ->where('push_token', $request->validated('push_token'))
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => __('devices.removed')]);
    }
}
