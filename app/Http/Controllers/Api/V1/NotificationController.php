<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notifications\IndexNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    /**
     * List the authenticated user's notifications, newest first, with the total unread count in the meta.
     */
    public function index(IndexNotificationRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return NotificationResource::collection($notifications)
            ->additional(['meta' => ['unread_count' => $user->notifications()->whereNull('read_at')->count()]]);
    }

    /**
     * Mark one of the user's notifications as read.
     */
    public function read(Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === request()->user()->id, 404);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json([
            'message' => __('notifications.read'),
            'data' => new NotificationResource($notification->refresh()),
        ]);
    }

    /**
     * Mark all of the user's unread notifications as read.
     */
    public function readAll(Request $request): JsonResponse
    {
        $updated = $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'message' => __('notifications.read_all'),
            'data' => ['updated' => $updated],
        ]);
    }
}
