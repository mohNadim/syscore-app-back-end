<?php

namespace App\Models;

use Database\Factories\FacilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'name_ar', 'name_en', 'description_ar', 'description_en', 'images', 'sort_order', 'is_active'])]
class Facility extends Model
{
    /** @use HasFactory<FacilityFactory> */
    use HasFactory;

    /**
     * The table has no timestamp columns.
     */
    public $timestamps = false;

    /**
     * Get the spaces within this facility.
     *
     * @return HasMany<Space, $this>
     */
    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    /**
     * Get the bookable units within this facility.
     *
     * @return HasMany<BookableUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(BookableUnit::class);
    }

    /**
     * Get the weekly operating hours of this facility.
     *
     * @return HasMany<OperatingHour, $this>
     */
    public function operatingHours(): HasMany
    {
        return $this->hasMany(OperatingHour::class);
    }

    /**
     * Get the closures that apply to this facility.
     *
     * @return HasMany<Closure, $this>
     */
    public function closures(): HasMany
    {
        return $this->hasMany(Closure::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'images' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
