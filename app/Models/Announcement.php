<?php

namespace App\Models;

use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['audience', 'academy_id', 'user_category_id', 'title_ar', 'title_en', 'body_ar', 'body_en', 'sent_by_staff_id', 'sent_at'])]
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
     * Get the user category this announcement targets.
     *
     * @return BelongsTo<UserCategory, $this>
     */
    public function userCategory(): BelongsTo
    {
        return $this->belongsTo(UserCategory::class);
    }

    /**
     * Get the explicitly targeted users (only for the "users" audience).
     *
     * @return BelongsToMany<User, $this>
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_recipients', 'announcement_id', 'user_id');
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
     * Active users in the announcement audience: everyone for "all"; for "academy", guardians of active children
     * with an active enrollment and an active subscription in one of that academy's groups; for "category", members
     * of the user category; for "users", the explicitly stored recipients.
     *
     * @return Builder<User>
     */
    public function audienceUsers(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->when($this->audience === 'academy', fn (Builder $query) => $query->whereHas('children', fn (Builder $children) => $children
                ->where('is_active', true)
                ->whereHas('enrollments', fn (Builder $enrollments) => $enrollments
                    ->where('status', 'active')
                    ->whereHas('group', fn (Builder $group) => $group->where('academy_id', $this->academy_id))
                    ->whereHas('subscriptions', fn (Builder $subscriptions) => $subscriptions->where('status', 'active')))))
            ->when($this->audience === 'category', fn (Builder $query) => $query->whereHas('categories', fn (Builder $categories) => $categories->where('user_categories.id', $this->user_category_id)))
            ->when($this->audience === 'users', fn (Builder $query) => $query->whereIn('id', $this->recipients()->select('users.id')));
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
