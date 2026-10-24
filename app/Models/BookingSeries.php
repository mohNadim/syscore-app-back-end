<?php

namespace App\Models;

use Database\Factories\BookingSeriesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'guest_name', 'guest_phone', 'unit_id', 'sport_id', 'weekday', 'start_time', 'duration_minutes', 'first_date', 'last_date', 'status', 'created_by_staff_id', 'cancelled_at'])]
class BookingSeries extends Model
{
    /** @use HasFactory<BookingSeriesFactory> */
    use HasFactory;

    protected $table = 'booking_series';

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'duration_minutes' => 'integer',
            'first_date' => 'date',
            'last_date' => 'date',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<BookableUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(BookableUnit::class);
    }

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'series_id');
    }
}
