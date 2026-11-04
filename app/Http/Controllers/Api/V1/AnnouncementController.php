<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserAnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * Show one announcement addressed to the authenticated user's audience; 404 when it is not addressed to them.
     */
    public function show(Request $request, Announcement $announcement): UserAnnouncementResource
    {
        abort_unless($announcement->audienceUsers()->whereKey($request->user()->id)->exists(), 404);

        return new UserAnnouncementResource($announcement);
    }
}
