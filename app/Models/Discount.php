<?php

namespace App\Models;

use Database\Factories\DiscountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name_ar', 'name_en', 'type', 'value', 'target', 'starts_at', 'ends_at', 'time_from', 'time_to', 'weekdays', 'is_active', 'notify_users', 'created_by_staff_id'])]
class Discount extends Model
{
    /** @use HasFactory<DiscountFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'weekdays' => 'integer',
            'is_active' => 'boolean',
            'notify_users' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Convert a list of weekdays (0 = Sunday ... 6 = Saturday) to the stored bitmask; null for "every day".
     *
     * @param  array<int, int>|null  $weekdays
     */
    public static function weekdaysToMask(?array $weekdays): ?int
    {
        if ($weekdays === null || $weekdays === []) {
            return null;
        }

        return array_reduce($weekdays, fn (int $mask, int $weekday) => $mask | (1 << $weekday), 0);
    }

    /**
     * The weekdays this discount applies on as a sorted list, or null when it applies every day.
     *
     * @return list<int>|null
     */
    public function weekdayList(): ?array
    {
        if ($this->weekdays === null) {
            return null;
        }

        return array_values(array_filter(range(0, 6), fn (int $weekday) => ($this->weekdays & (1 << $weekday)) !== 0));
    }

    /**
     * Whether the discount is valid on the given weekday (0 = Sunday ... 6 = Saturday).
     */
    public function appliesOnWeekday(int $weekday): bool
    {
        return $this->weekdays === null || ($this->weekdays & (1 << $weekday)) !== 0;
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * @return BelongsToMany<UserCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(UserCategory::class, 'discount_categories', 'discount_id', 'category_id');
    }

    /**
     * @return BelongsToMany<Sport, $this>
     */
    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class, 'discount_sports', 'discount_id', 'sport_id');
    }

    /**
     * @return BelongsToMany<Academy, $this>
     */
    public function academies(): BelongsToMany
    {
        return $this->belongsToMany(Academy::class, 'discount_academies', 'discount_id', 'academy_id');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'discount_id');
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'discount_id');
    }
}
