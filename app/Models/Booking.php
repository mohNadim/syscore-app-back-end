<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['booking_number', 'user_id', 'guest_name', 'guest_phone', 'unit_id', 'sport_id', 'series_id', 'starts_at', 'ends_at', 'status', 'source', 'payment_mode', 'base_price', 'discount_id', 'discount_amount', 'final_price', 'deposit_amount', 'deposit_due_at', 'paid_amount', 'cancelled_at', 'cancelled_by_user_id', 'cancelled_by_staff_id', 'cancel_reason', 'refund_amount', 'reminder_sent_at', 'created_by_staff_id'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'base_price' => 'integer',
            'discount_amount' => 'integer',
            'final_price' => 'integer',
            'deposit_amount' => 'integer',
            'deposit_due_at' => 'datetime',
            'paid_amount' => 'integer',
            'cancelled_at' => 'datetime',
            'refund_amount' => 'integer',
            'reminder_sent_at' => 'datetime',
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
     * @return BelongsTo<BookingSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(BookingSeries::class, 'series_id');
    }

    /**
     * @return BelongsTo<Discount, $this>
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function cancelledByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'cancelled_by_staff_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'booking_id');
    }

    /**
     * @return HasMany<SpaceReservation, $this>
     */
    public function spaceReservations(): HasMany
    {
        return $this->hasMany(SpaceReservation::class, 'booking_id');
    }
}
