<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\FacilityResource;
use App\Models\Facility;
use App\Services\DisplayTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FacilityController extends Controller
{
    /**
     * List active facilities with their active units, the units' active sports and the facility spaces.
     */
    public function index(): AnonymousResourceCollection
    {
        $facilities = Facility::query()
            ->where('is_active', true)
            ->with($this->relations())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return FacilityResource::collection($facilities);
    }

    /**
     * Show one active facility; an inactive one responds with 404.
     */
    public function show(Facility $facility): FacilityResource
    {
        abort_unless($facility->is_active, 404);

        return new FacilityResource($facility->load($this->relations()));
    }

    /**
     * @return array<string, \Closure>|list<string>
     */
    private function relations(): array
    {
        $today = CarbonImmutable::now(app(DisplayTimezone::class)->name())->toDateString();

        return [
            'spaces',
            'operatingHours',
            'units' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id'),
            'units.sports' => fn ($query) => $query->where('sports.is_active', true)->orderBy('sports.id'),
            'units.spaces',
            'units.priceRules' => fn ($query) => $query
                ->where(fn ($rules) => $rules->whereNull('valid_to')->orWhere('valid_to', '>=', $today)),
        ];
    }
}
