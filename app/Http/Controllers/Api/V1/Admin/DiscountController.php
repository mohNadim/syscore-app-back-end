<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\DeletesWithoutBreakingReferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Discounts\IndexDiscountRequest;
use App\Http\Requests\Api\V1\Admin\Discounts\StoreDiscountRequest;
use App\Http\Requests\Api\V1\Admin\Discounts\UpdateDiscountRequest;
use App\Http\Resources\DiscountResource;
use App\Models\Discount;
use App\Services\AuditLogger;
use App\Services\DisplayTimezone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DiscountController extends Controller
{
    use DeletesWithoutBreakingReferences;

    /**
     * The eligibility relations that are loaded with every discount.
     *
     * @var list<string>
     */
    private const RELATIONS = ['categories', 'sports', 'academies'];

    /**
     * List discounts, newest start first, optionally filtered by state, target and a local moment they are valid at.
     */
    public function index(IndexDiscountRequest $request, DisplayTimezone $timezone): AnonymousResourceCollection
    {
        $discounts = Discount::query()
            ->with(self::RELATIONS)
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('target'), fn ($query) => $query->where('target', $request->string('target')->toString()))
            ->when($request->filled('active_at'), function ($query) use ($request, $timezone) {
                $moment = $timezone->localToUtc($request->string('active_at')->toString());

                $query->where('starts_at', '<=', $moment)->where('ends_at', '>=', $moment);
            })
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return DiscountResource::collection($discounts);
    }

    /**
     * Create a discount together with its eligibility restrictions.
     */
    public function store(StoreDiscountRequest $request, AuditLogger $audit): JsonResponse
    {
        $discount = DB::transaction(function () use ($request): Discount {
            $discount = Discount::create([
                ...$request->discountAttributes(),
                'created_by_staff_id' => $request->user()->id,
            ]);

            foreach ($request->relationIds() as $relation => $ids) {
                $discount->{$relation}()->sync($ids);
            }

            return $discount->refresh();
        });

        $discount->load(self::RELATIONS);
        $audit->log('discount.created', $discount, null, $this->snapshot($discount));

        return response()->json([
            'message' => __('admin_discounts.created'),
            'data' => new DiscountResource($discount),
        ], 201);
    }

    /**
     * Show one discount.
     */
    public function show(Discount $discount): DiscountResource
    {
        return new DiscountResource($discount->load(self::RELATIONS));
    }

    /**
     * Update a discount; restriction lists that are sent replace the stored ones.
     */
    public function update(UpdateDiscountRequest $request, Discount $discount, AuditLogger $audit): JsonResponse
    {
        $discount->load(self::RELATIONS);
        $before = $this->snapshot($discount);

        DB::transaction(function () use ($request, $discount): void {
            $discount->update($request->discountAttributes());

            foreach ($request->relationIds() as $relation => $ids) {
                $discount->{$relation}()->sync($ids);
            }
        });

        $discount->refresh()->load(self::RELATIONS);
        $after = $this->snapshot($discount);

        $changed = array_keys(array_filter($after, fn ($value, $key) => $value !== $before[$key], ARRAY_FILTER_USE_BOTH));

        if ($changed !== []) {
            $audit->log('discount.updated', $discount, array_intersect_key($before, array_flip($changed)), array_intersect_key($after, array_flip($changed)));
        }

        return response()->json([
            'message' => __('admin_discounts.updated'),
            'data' => new DiscountResource($discount),
        ]);
    }

    /**
     * Delete a discount that no booking or subscription used; its restriction rows go with it.
     *
     * @throws ConflictHttpException when a booking or subscription references the discount
     */
    public function destroy(Discount $discount, AuditLogger $audit): JsonResponse
    {
        $inUseMessage = __('admin_discounts.discount_in_use');

        if ($discount->bookings()->exists() || $discount->subscriptions()->exists()) {
            throw new ConflictHttpException($inUseMessage);
        }

        $discount->load(self::RELATIONS);
        $before = $this->snapshot($discount);

        $this->deleteOrConflict($discount, $inUseMessage, function () use ($discount): void {
            foreach (self::RELATIONS as $relation) {
                $discount->{$relation}()->detach();
            }
        });

        $audit->log('discount.deleted', $discount, $before, null);

        return response()->json(['message' => __('admin_discounts.deleted')]);
    }

    /**
     * The discount columns plus its restriction id lists, used for audit rows.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Discount $discount): array
    {
        $snapshot = collect($discount->getAttributes())->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value)->all();

        foreach (['categories' => 'category_ids', 'sports' => 'sport_ids', 'academies' => 'academy_ids'] as $relation => $key) {
            $snapshot[$key] = $discount->{$relation}->pluck('id')->sort()->values()->all();
        }

        return $snapshot;
    }
}
