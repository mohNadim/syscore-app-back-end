<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Enrollments\IndexEnrollmentRequest;
use App\Http\Requests\Api\V1\Enrollments\StoreEnrollmentRequest;
use App\Http\Resources\EligibleGroupResource;
use App\Http\Resources\EnrollmentResource;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\AcademyGroup;
use App\Models\Child;
use App\Models\Enrollment;
use App\Models\Subscription;
use App\Services\Academies\EnrollmentService;
use App\Services\Academies\SubscriptionService;
use App\Services\DisplayTimezone;
use App\Services\Pricing\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EnrollmentController extends Controller
{
    /**
     * Relations loaded for every enrollment response.
     *
     * @var list<string>
     */
    private const WITH = ['child', 'group.academy.sport', 'latestSubscription.discount'];

    /**
     * List the active groups the child is old enough for, of the right gender, with seats, price and the child's status in each.
     */
    public function eligibleGroups(Child $child, EnrollmentService $enrollments, PricingService $pricing, DisplayTimezone $timezone): AnonymousResourceCollection
    {
        Gate::authorize('view', $child);

        $age = $enrollments->ageOf($child);
        $today = now($timezone->name())->toDateString();

        $groups = $enrollments->withSeatCounts(AcademyGroup::query())
            ->where('is_active', true)
            ->whereHas('academy', fn ($academy) => $academy->where('is_active', true))
            ->where('age_min', '<=', $age)
            ->where('age_max', '>=', $age)
            ->whereIn('gender', ['mixed', $child->gender])
            ->with([
                'academy.sport',
                'coach',
                'schedules' => fn ($schedules) => $schedules
                    ->where(fn ($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
                    ->with('unit')
                    ->orderBy('weekday')
                    ->orderBy('start_time'),
            ])
            ->orderBy('id')
            ->get();

        $enrolledGroupIds = $child->enrollments()->where('status', 'active')->pluck('group_id')->all();
        $entryStatuses = $child->waitlistEntries()->whereIn('status', ['waiting', 'offered'])->pluck('status', 'group_id');

        foreach ($groups as $group) {
            $group->setAttribute('seats_left', $enrollments->seatsLeft($group));
            $group->setAttribute('expected_position', $enrollments->expectedPosition($group));
            $group->setAttribute('price', $pricing->quoteSubscription($group, $child->user)->toArray());
            $group->setAttribute('child_status', match (true) {
                in_array($group->id, $enrolledGroupIds, true) => 'enrolled',
                ($entryStatuses[$group->id] ?? null) === 'offered' => 'offered',
                ($entryStatuses[$group->id] ?? null) === 'waiting' => 'waitlisted',
                default => null,
            });
        }

        return EligibleGroupResource::collection($groups);
    }

    /**
     * List enrollments of the user's own children.
     */
    public function index(IndexEnrollmentRequest $request): AnonymousResourceCollection
    {
        $enrollments = Enrollment::query()
            ->whereHas('child', fn ($child) => $child->where('user_id', $request->user()->id))
            ->when($request->filled('child_id'), fn ($query) => $query->where('child_id', $request->integer('child_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->with(self::WITH)
            ->orderByDesc('id')
            ->get();

        return EnrollmentResource::collection($enrollments);
    }

    /**
     * Show one enrollment of the user's own children.
     */
    public function show(Enrollment $enrollment): EnrollmentResource
    {
        Gate::authorize('view', $enrollment);

        return new EnrollmentResource($enrollment->load(self::WITH));
    }

    /**
     * Cancel an enrollment: at once when nothing is paid, otherwise scheduled for the end of the paid period.
     *
     * @throws BusinessRuleException when the enrollment is not active or its end is already scheduled
     */
    public function cancel(Enrollment $enrollment, SubscriptionService $subscriptions): JsonResponse
    {
        Gate::authorize('cancel', $enrollment);

        $cancelled = $subscriptions->cancelEnrollmentByUser($enrollment)->load(self::WITH);

        return response()->json([
            'message' => $cancelled->status === 'active' ? __('enrollments.cancellation_scheduled') : __('enrollments.cancelled'),
            'data' => new EnrollmentResource($cancelled),
        ]);
    }

    /**
     * Undo a scheduled cancellation that has not taken effect yet.
     *
     * @throws BusinessRuleException when no cancellation is scheduled
     */
    public function undoCancel(Enrollment $enrollment, SubscriptionService $subscriptions): JsonResponse
    {
        Gate::authorize('cancel', $enrollment);

        return response()->json([
            'message' => __('enrollments.cancellation_undone'),
            'data' => new EnrollmentResource($subscriptions->undoEnrollmentCancellation($enrollment)->load(self::WITH)),
        ]);
    }

    /**
     * Enroll one of the user's children in a group, or join its waitlist (202) when no seat is left.
     *
     * @throws ValidationException when the child is not eligible for the group
     * @throws ConflictHttpException when the child is already enrolled or already waiting
     * @throws BusinessRuleException when the child's waitlist offer has expired
     */
    public function store(StoreEnrollmentRequest $request, EnrollmentService $enrollments): JsonResponse
    {
        $child = Child::query()->findOrFail($request->integer('child_id'));
        Gate::authorize('view', $child);

        $result = $enrollments->enroll(
            $child,
            AcademyGroup::query()->findOrFail($request->integer('group_id')),
            paymentMethod: $request->input('payment_method') ?? Subscription::PAYMENT_METHOD_SHAM_CASH,
        );

        if ($result->isEnrolled()) {
            return response()->json([
                'message' => __('enrollments.enrolled'),
                'data' => new EnrollmentResource($result->enrollment->load(self::WITH)),
            ], 201);
        }

        $entry = $result->waitlistEntry->load(['child', 'group.academy.sport']);
        $entry->setAttribute('position', $enrollments->waitlistPosition($entry));

        return response()->json([
            'message' => __('enrollments.waitlisted'),
            'data' => new WaitlistEntryResource($entry),
        ], 202);
    }
}
