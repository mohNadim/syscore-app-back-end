<?php

namespace App\Models;

use Database\Factories\CoachFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['full_name_ar', 'full_name_en', 'photo_url', 'bio_ar', 'bio_en', 'sport_id', 'is_active', 'sort_order'])]
class Coach extends Model
{
    /** @use HasFactory<CoachFactory> */
    use HasFactory;

    /**
     * The table has no timestamp columns.
     */
    public $timestamps = false;

    /**
     * Get the sport this coach teaches.
     *
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
