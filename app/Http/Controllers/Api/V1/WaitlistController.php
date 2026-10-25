<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\WaitlistEntry;
use App\Services\Academies\EnrollmentService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class WaitlistController extends Controller
{
    /**
     * List the waiting and offered waitlist entries of the user's children with their queue position.
     */
    public function index(Request $request, EnrollmentService $enrollments): AnonymousResourceCollection
    {
        $entries = WaitlistEntry::query()
            ->whereHas('child', fn ($child) => $child->where('user_id', $request->user()->id))
            ->whereIn('status', ['waiting', 'offered'])
            ->with(['child', 'group.academy.sport'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $entries->each(fn (WaitlistEntry $entry) => $entry->setAttribute('position', $enrollments->waitlistPosition($entry)));

        return WaitlistEntryResource::collection($entries);
    }

    /**
     * Leave a waitlist.
     *
     * @throws UnprocessableEntityHttpException when the entry is no longer waiting or offered
     */
    public function destroy(WaitlistEntry $waitlistEntry, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('delete', $waitlistEntry);

        if (! in_array($waitlistEntry->status, ['waiting', 'offered'], true)) {
            throw new UnprocessableEntityHttpException(__('enrollments.waitlist_not_removable'));
        }

        $waitlistEntry->update(['status' => 'removed']);
        $audit->logChange('waitlist.left', $waitlistEntry);

        return response()->json(['message' => __('enrollments.waitlist_left')]);
    }
}
