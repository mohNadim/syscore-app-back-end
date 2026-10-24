<?php

namespace App\Models;

use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['full_name', 'username', 'phone', 'password_hash', 'role_id', 'is_active', 'last_login_at'])]
#[Hidden(['password_hash'])]
class Staff extends Authenticatable
{
    /** @use HasFactory<StaffFactory> */
    use HasFactory;

    protected $table = 'staff';

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the name of the password attribute for the user.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function createdBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'created_by_staff_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function recordedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'recorded_by_staff_id');
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function processedRefunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'processed_by_staff_id');
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function markedAttendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'marked_by_staff_id');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function createdEnrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'created_by_staff_id');
    }

    /**
     * @return HasMany<Discount, $this>
     */
    public function createdDiscounts(): HasMany
    {
        return $this->hasMany(Discount::class, 'created_by_staff_id');
    }

    /**
     * @return HasMany<BookingSeries, $this>
     */
    public function createdBookingSeries(): HasMany
    {
        return $this->hasMany(BookingSeries::class, 'created_by_staff_id');
    }
}
