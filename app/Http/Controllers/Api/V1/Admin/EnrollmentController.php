<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Enrollments\EndEnrollmentRequest;
use App\Http\Requests\Api\V1\Admin\Enrollments\IndexEnrollmentRequest;
use App\Http\Requests\Api\V1\Admin\Enrollments\StoreEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\AcademyGroup;
use App\Models\Child;
use App\Models\Enrollment;
use App\Services\Academies\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class EnrollmentController extends Controller
{
    /**
     * Relations loaded for every enrollment response.
     *
     * @var list<string>
     */
    private const WITH = ['child.user', 'group.academy.sport', 'latestSubscription.discount'];

    /**
     * List enrollments, newest first.
     */
    public function index(IndexEnrollmentRequest $request): AnonymousResourceCollection
    {
        $enrollments = Enrollment::query()
            ->when($request->filled('group_id'), fn ($query) => $query->where('group_id', $request->integer('group_id')))
            ->when($request->filled('child_id'), fn ($query) => $query->where('child_id', $request->integer('child_id')))
            ->when($request->filled('user_id'), fn ($query) => $query->whereHas('child', fn ($child) => $child->where('user_id', $request->integer('user_id'))))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->with(self::WITH)
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return EnrollmentResource::collection($enrollments);
    }

    /**
     * Show one enrollment.
     */
    public function show(Enrollment $enrollment): EnrollmentResource
    {
        return new EnrollmentResource($enrollment->load(self::WITH));
    }

    /**
     * Enroll a child on behalf of staff, or waitlist it (202) when the group is full and capacity is not overridden.
     *
     * @throws ValidationException when the child is not eligible for the group
     * @throws ConflictHttpException when the child is already enrolled or already waiting
     */
    public function store(StoreEnrollmentRequest $request, EnrollmentService $enrollments): JsonResponse
    {
        $result = $enrollments->enroll(
            Child::query()->with('user')->findOrFail($request->integer('child_id')),
            AcademyGroup::query()->findOrFail($request->integer('group_id')),
            $request->user(),
            $request->boolean('override_capacity'),
        );

        if ($result->isEnrolled()) {
            return response()->json([
                'message' => __('admin_enrollments.created'),
                'data' => new EnrollmentResource($result->enrollment->load(self::WITH)),
            ], 201);
        }

        $entry = $result->waitlistEntry->load(['child.user', 'group.academy.sport']);
        $entry->setAttribute('position', $enrollments->waitlistPosition($entry));

        return response()->json([
            'message' => __('admin_enrollments.waitlisted'),
            'data' => new WaitlistEntryResource($entry),
        ], 202);
    }

    /**
     * End an active enrollment immediately.
     *
     * @throws UnprocessableEntityHttpException when the enrollment is not active
     */
    public function end(EndEnrollmentRequest $request, Enrollment $enrollment, EnrollmentService $enrollments): JsonResponse
    {
        $ended = $enrollments->end($enrollment, $request->input('reason'));

        return response()->json([
            'message' => __('admin_enrollments.ended'),
            'data' => new EnrollmentResource($ended->load(self::WITH)),
        ]);
    }
}
