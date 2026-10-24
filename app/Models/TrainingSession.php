<?php

namespace App\Models;

use Database\Factories\TrainingSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'schedule_id', 'unit_id', 'coach_id', 'starts_at', 'ends_at', 'status', 'original_starts_at', 'change_reason'])]
class TrainingSession extends Model
{
    /** @use HasFactory<TrainingSessionFactory> */
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
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'original_starts_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AcademyGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(AcademyGroup::class);
    }

    /**
     * @return BelongsTo<GroupSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(GroupSchedule::class);
    }

    /**
     * @return BelongsTo<BookableUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(BookableUnit::class);
    }

    /**
     * @return BelongsTo<Coach, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'session_id');
    }

    /**
     * @return HasMany<SpaceReservation, $this>
     */
    public function spaceReservations(): HasMany
    {
        return $this->hasMany(SpaceReservation::class, 'session_id');
    }
}
