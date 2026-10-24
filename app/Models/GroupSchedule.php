<?php

namespace App\Models;

use Database\Factories\GroupScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'unit_id', 'weekday', 'start_time', 'end_time', 'valid_from', 'valid_to'])]
class GroupSchedule extends Model
{
    /** @use HasFactory<GroupScheduleFactory> */
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
            'weekday' => 'integer',
            'valid_from' => 'date',
            'valid_to' => 'date',
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
     * @return BelongsTo<BookableUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(BookableUnit::class);
    }

    /**
     * @return HasMany<TrainingSession, $this>
     */
    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'schedule_id');
    }
}
