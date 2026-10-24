<?php

namespace App\Models;

use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['audience', 'academy_id', 'title_ar', 'title_en', 'body_ar', 'body_en', 'sent_by_staff_id', 'sent_at'])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    /**
     * The table has no updated_at column.
     */
    public const UPDATED_AT = null;

    /**
     * Get the academy this announcement targets.
     *
     * @return BelongsTo<Academy, $this>
     */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /**
     * Get the staff member who sent this announcement.
     *
     * @return BelongsTo<Staff, $this>
     */
    public function sentByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'sent_by_staff_id');
    }

    /**
     * Get the notifications generated from this announcement.
     *
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }
}
