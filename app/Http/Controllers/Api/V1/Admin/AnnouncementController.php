<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Announcements\IndexAnnouncementRequest;
use App\Http\Requests\Api\V1\Admin\Announcements\StoreAnnouncementRequest;
use App\Http\Resources\AdminAnnouncementResource;
use App\Jobs\SendAnnouncement;
use App\Models\Announcement;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    /**
     * List announcements, newest first, paginated; optionally filtered by audience, academy and user category.
     */
    public function index(IndexAnnouncementRequest $request): AnonymousResourceCollection
    {
        $announcements = Announcement::query()
            ->when($request->filled('audience'), fn ($query) => $query->where('audience', $request->string('audience')->toString()))
            ->when($request->filled('academy_id'), fn ($query) => $query->where('academy_id', $request->integer('academy_id')))
            ->when($request->filled('user_category_id'), fn ($query) => $query->where('user_category_id', $request->integer('user_category_id')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return AdminAnnouncementResource::collection($announcements);
    }

    /**
     * Create an announcement, stamp it as sent and queue its delivery to the audience.
     */
    public function store(StoreAnnouncementRequest $request, AuditLogger $audit): JsonResponse
    {
        $announcement = DB::transaction(function () use ($request): Announcement {
            $announcement = Announcement::create([
                ...$request->announcementAttributes(),
                'sent_by_staff_id' => $request->user()->getKey(),
                'sent_at' => now(),
            ]);

            if ($announcement->audience === 'users') {
                $announcement->recipients()->attach($request->recipientUserIds());
            }

            return $announcement->refresh();
        });

        $audit->log('announcement.created', $announcement, null, $announcement->toArray());

        SendAnnouncement::dispatch($announcement);

        return response()->json([
            'message' => __('admin_announcements.announcement_sent'),
            'data' => new AdminAnnouncementResource($announcement),
        ], 201);
    }

    /**
     * Show one announcement with its recipient and push-delivered counts.
     */
    public function show(Announcement $announcement): AdminAnnouncementResource
    {
        $announcement->loadCount([
            'notifications',
            'notifications as push_sent_notifications_count' => fn ($query) => $query->whereNotNull('push_sent_at'),
        ]);

        return new AdminAnnouncementResource($announcement);
    }
}
