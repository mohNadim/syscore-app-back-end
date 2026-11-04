<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Discounts\IndexDiscountRequest;
use App\Http\Resources\UserDiscountResource;
use App\Models\Discount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DiscountController extends Controller
{
    /**
     * List the discounts that are active now and open to the authenticated user's categories, newest first.
     */
    public function index(IndexDiscountRequest $request): AnonymousResourceCollection
    {
        $discounts = $this->visibleTo($request)
            ->with(['sports', 'academies'])
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))->withQueryString();

        return UserDiscountResource::collection($discounts);
    }

    /**
     * Show one currently active discount open to the user; 404 otherwise.
     */
    public function show(Request $request, int $discount): UserDiscountResource
    {
        return new UserDiscountResource(
            $this->visibleTo($request)->with(['sports', 'academies'])->findOrFail($discount)
        );
    }

    /**
     * Discounts that are active and inside their validity period, either open to everyone or to one of the user's active categories.
     *
     * @return Builder<Discount>
     */
    private function visibleTo(Request $request): Builder
    {
        $categoryIds = $request->user()->categories()->where('user_categories.is_active', true)->pluck('user_categories.id');

        return Discount::query()
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->where(fn (Builder $query) => $query
                ->whereDoesntHave('categories')
                ->orWhereHas('categories', fn (Builder $categories) => $categories->whereIn('user_categories.id', $categoryIds)));
    }
}
