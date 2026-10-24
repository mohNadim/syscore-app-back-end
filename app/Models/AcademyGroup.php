<?php

namespace App\Models;

use Database\Factories\AcademyGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'coach_id', 'name_ar', 'name_en', 'age_min', 'age_max', 'gender', 'capacity', 'level', 'monthly_price', 'subscription_days', 'is_active'])]
class AcademyGroup extends Model
{
    /** @use HasFactory<AcademyGroupFactory> */
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
            'age_min' => 'integer',
            'age_max' => 'integer',
            'capacity' => 'integer',
            'monthly_price' => 'integer',
            'subscription_days' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Academy, $this>
     */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /**
     * @return BelongsTo<Coach, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    /**
     * @return HasMany<GroupSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(GroupSchedule::class, 'group_id');
    }

    /**
     * @return HasMany<TrainingSession, $this>
     */
    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'group_id');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'group_id');
    }

    /**
     * @return HasMany<WaitlistEntry, $this>
     */
    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class, 'group_id');
    }
}
