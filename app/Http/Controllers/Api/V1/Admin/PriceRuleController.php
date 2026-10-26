<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\PriceRules\IndexPriceRuleRequest;
use App\Http\Requests\Api\V1\Admin\PriceRules\StorePriceRuleRequest;
use App\Http\Requests\Api\V1\Admin\PriceRules\UpdatePriceRuleRequest;
use App\Http\Resources\PriceRuleResource;
use App\Models\PriceRule;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PriceRuleController extends Controller
{
    /**
     * List price rules ordered by unit, weekday (every-day rules first) and start time.
     */
    public function index(IndexPriceRuleRequest $request): AnonymousResourceCollection
    {
        $rules = PriceRule::query()
            ->with('unit')
            ->when($request->filled('unit_id'), fn ($query) => $query->where('unit_id', $request->integer('unit_id')))
            ->when($request->filled('weekday'), fn ($query) => $query->where(
                fn ($inner) => $inner->whereNull('weekday')->orWhere('weekday', $request->integer('weekday')),
            ))
            ->when($request->filled('is_peak'), fn ($query) => $query->where('is_peak', $request->boolean('is_peak')))
            ->when($request->filled('active_on'), function ($query) use ($request) {
                $date = $request->string('active_on')->toString();

                $query->where('valid_from', '<=', $date)
                    ->where(fn ($inner) => $inner->whereNull('valid_to')->orWhere('valid_to', '>=', $date));
            })
            ->orderBy('unit_id')
            ->orderByRaw('weekday is not null')
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        return PriceRuleResource::collection($rules);
    }

    /**
     * Create a price rule.
     */
    public function store(StorePriceRuleRequest $request, AuditLogger $audit): JsonResponse
    {
        $rule = PriceRule::create($request->priceRuleAttributes())->refresh();

        $audit->log('price_rule.created', $rule, null, $rule->toArray());

        return response()->json([
            'message' => __('admin_price_rules.created'),
            'data' => new PriceRuleResource($rule->load('unit')),
        ], 201);
    }

    /**
     * Show one price rule.
     */
    public function show(PriceRule $priceRule): PriceRuleResource
    {
        return new PriceRuleResource($priceRule->load('unit'));
    }

    /**
     * Update a price rule.
     */
    public function update(UpdatePriceRuleRequest $request, PriceRule $priceRule, AuditLogger $audit): JsonResponse
    {
        $priceRule->update($request->priceRuleAttributes());

        if ($priceRule->wasChanged()) {
            $audit->logChange('price_rule.updated', $priceRule);
        }

        return response()->json([
            'message' => __('admin_price_rules.updated'),
            'data' => new PriceRuleResource($priceRule->load('unit')),
        ]);
    }

    /**
     * Delete a price rule permanently; bookings keep their own price snapshot.
     */
    public function destroy(PriceRule $priceRule, AuditLogger $audit): JsonResponse
    {
        $before = $priceRule->toArray();

        $priceRule->delete();

        $audit->log('price_rule.deleted', $priceRule, $before, null);

        return response()->json(['message' => __('admin_price_rules.deleted')]);
    }
}
