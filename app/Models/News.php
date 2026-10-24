<?php

namespace App\Models;

use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title_ar', 'title_en', 'body_ar', 'body_en', 'cover_url', 'is_published', 'published_at', 'created_by_staff_id'])]
class News extends Model
{
    /** @use HasFactory<NewsFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'news';

    /**
     * The table has no updated_at column.
     */
    public const UPDATED_AT = null;

    /**
     * Get the staff member who created this article.
     *
     * @return BelongsTo<Staff, $this>
     */
    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
