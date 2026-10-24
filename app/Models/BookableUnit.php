<?php

namespace App\Models;

use Database\Factories\BookableUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['facility_id', 'code', 'name_ar', 'name_en', 'is_active', 'sort_order'])]
class BookableUnit extends Model
{
    /** @use HasFactory<BookableUnitFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsToMany<Space, $this>
     */
    public function spaces(): BelongsToMany
    {
        return $this->belongsToMany(Space::class, 'unit_spaces', 'unit_id', 'space_id');
    }

    /**
     * @return BelongsToMany<Sport, $this>
     */
    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class, 'unit_sports', 'unit_id', 'sport_id');
    }

    /**
     * @return HasMany<PriceRule, $this>
     */
    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class, 'unit_id');
    }

    /**
     * @return HasMany<GroupSchedule, $this>
     */
    public function groupSchedules(): HasMany
    {
        return $this->hasMany(GroupSchedule::class, 'unit_id');
    }

    /**
     * @return HasMany<TrainingSession, $this>
     */
    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'unit_id');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'unit_id');
    }

    /**
     * @return HasMany<BookingSeries, $this>
     */
    public function bookingSeries(): HasMany
    {
        return $this->hasMany(BookingSeries::class, 'unit_id');
    }
}
