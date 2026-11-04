<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
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
     * Accept the seat offered to a child: enrolls it in the group exactly as `POST /enrollments` does.
     *
     * @throws BusinessRuleException when the entry has no open offer (waitlist_offer_not_available) or the offer expired (waitlist_offer_expired)
     */
    public function accept(WaitlistEntry $waitlistEntry, EnrollmentService $enrollments): JsonResponse
    {
        Gate::authorize('accept', $waitlistEntry);

        if ($waitlistEntry->status !== 'offered') {
            throw new BusinessRuleException('waitlist_offer_not_available', __('enrollments.waitlist_offer_not_available'));
        }

        $result = $enrollments->enroll($waitlistEntry->child, $waitlistEntry->group);

        return response()->json([
            'message' => __('enrollments.enrolled'),
            'data' => new EnrollmentResource($result->enrollment->load(['child', 'group.academy.sport', 'latestSubscription.discount'])),
        ], 201);
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
