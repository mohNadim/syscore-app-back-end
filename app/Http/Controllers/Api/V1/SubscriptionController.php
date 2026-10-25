<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Subscriptions\IndexSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\Academies\SubscriptionService;
use App\Services\DisplayTimezone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SubscriptionController extends Controller
{
    /**
     * Relations loaded for every subscription response.
     *
     * @var list<string>
     */
    private const WITH = ['discount', 'enrollment.child', 'enrollment.group.academy.sport'];

    /**
     * List subscriptions of the user's own children, newest first.
     */
    public function index(IndexSubscriptionRequest $request): AnonymousResourceCollection
    {
        $subscriptions = Subscription::query()
            ->whereHas('enrollment.child', fn ($child) => $child->where('user_id', $request->user()->id))
            ->when($request->filled('child_id'), fn ($query) => $query->whereHas('enrollment', fn ($enrollment) => $enrollment->where('child_id', $request->integer('child_id'))))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->with(self::WITH)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return SubscriptionResource::collection($subscriptions);
    }

    /**
     * Show one of the user's subscriptions.
     */
    public function show(Subscription $subscription): SubscriptionResource
    {
        Gate::authorize('view', $subscription);

        return new SubscriptionResource($subscription->load(self::WITH));
    }

    /**
     * Request the next month of a subscription; it must then be paid.
     *
     * @throws ConflictHttpException when the end is scheduled or a renewal is already pending
     * @throws UnprocessableEntityHttpException when the subscription cannot be renewed
     */
    public function renew(Subscription $subscription, SubscriptionService $subscriptions): JsonResponse
    {
        Gate::authorize('renew', $subscription);

        $renewal = $subscriptions->renew($subscription);

        return response()->json([
            'message' => __('subscriptions.renewal_requested'),
            'data' => new SubscriptionResource($renewal->load(self::WITH)),
        ], 201);
    }

    /**
     * Cancel an unpaid subscription now, or schedule the end of a paid one at the end of its period.
     *
     * @throws ConflictHttpException when the end is already scheduled
     * @throws UnprocessableEntityHttpException when the subscription is not cancellable
     */
    public function cancel(Subscription $subscription, SubscriptionService $subscriptions, DisplayTimezone $timezone): JsonResponse
    {
        Gate::authorize('cancel', $subscription);

        $cancelled = $subscriptions->cancelByUser($subscription)->load(self::WITH);

        $message = $cancelled->status === 'active'
            ? __('subscriptions.cancellation_scheduled', ['date' => $timezone->utcToLocalIso($cancelled->period_end)])
            : __('subscriptions.cancelled');

        return response()->json(['message' => $message, 'data' => new SubscriptionResource($cancelled)]);
    }
}
