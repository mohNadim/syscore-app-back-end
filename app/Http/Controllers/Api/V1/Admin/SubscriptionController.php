<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Enrollments\IndexSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\Academies\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SubscriptionController extends Controller
{
    /**
     * Relations loaded for every subscription response.
     *
     * @var list<string>
     */
    private const WITH = ['discount', 'enrollment.child.user', 'enrollment.group.academy.sport'];

    /**
     * List subscriptions, newest first; `expiring_within_days` keeps active ones ending from now until then.
     */
    public function index(IndexSubscriptionRequest $request): AnonymousResourceCollection
    {
        $subscriptions = Subscription::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('group_id'), fn ($query) => $query->whereHas('enrollment', fn ($enrollment) => $enrollment->where('group_id', $request->integer('group_id'))))
            ->when($request->filled('expiring_within_days'), fn ($query) => $query
                ->where('status', 'active')
                ->whereBetween('period_end', [now(), now()->addDays($request->integer('expiring_within_days'))]))
            ->with(self::WITH)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))->withQueryString();

        return SubscriptionResource::collection($subscriptions);
    }

    /**
     * Cancel a subscription immediately.
     *
     * @throws UnprocessableEntityHttpException when the subscription is expired or already cancelled
     */
    public function cancel(Subscription $subscription, SubscriptionService $subscriptions): JsonResponse
    {
        return response()->json([
            'message' => __('admin_enrollments.subscription_cancelled'),
            'data' => new SubscriptionResource($subscriptions->cancelNow($subscription)->load(self::WITH)),
        ]);
    }
}
