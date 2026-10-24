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
