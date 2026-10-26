<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\AcademyGroup;
use App\Models\WaitlistEntry;
use App\Services\Academies\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class WaitlistController extends Controller
{
    /**
     * List a group's waiting and offered entries in queue order, with the parent's phone.
     */
    public function index(AcademyGroup $group, EnrollmentService $enrollments): AnonymousResourceCollection
    {
        $entries = $group->waitlistEntries()
            ->whereIn('status', ['waiting', 'offered'])
            ->with('child.user')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $entries->each(fn (WaitlistEntry $entry) => $entry->setAttribute('position', $enrollments->waitlistPosition($entry)));

        return WaitlistEntryResource::collection($entries);
    }

    /**
     * Offer a free seat to a waiting child.
     *
     * @throws ConflictHttpException when the group has no free seat
     * @throws UnprocessableEntityHttpException when the entry is not waiting
     */
    public function offer(WaitlistEntry $waitlistEntry, EnrollmentService $enrollments): JsonResponse
    {
        $offered = $enrollments->offer($waitlistEntry)->load(['child.user', 'group']);

        return response()->json([
            'message' => __('admin_enrollments.offered'),
            'data' => new WaitlistEntryResource($offered),
        ]);
    }

    /**
     * Remove an entry from the waitlist.
     *
     * @throws UnprocessableEntityHttpException when the entry is already finished
     */
    public function remove(WaitlistEntry $waitlistEntry, EnrollmentService $enrollments): JsonResponse
    {
        $removed = $enrollments->removeFromWaitlist($waitlistEntry)->load(['child.user', 'group']);

        return response()->json([
            'message' => __('admin_enrollments.removed'),
            'data' => new WaitlistEntryResource($removed),
        ]);
    }
}
