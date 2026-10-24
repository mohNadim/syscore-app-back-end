<?php

namespace App\Models;

use Database\Factories\AcademyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sport_id', 'name_ar', 'name_en', 'description_ar', 'description_en', 'cover_url', 'is_active'])]
class Academy extends Model
{
    /** @use HasFactory<AcademyFactory> */
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * @return HasMany<AcademyGroup, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(AcademyGroup::class, 'academy_id');
    }

    /**
     * @return BelongsToMany<Discount, $this>
     */
    public function discounts(): BelongsToMany
    {
        return $this->belongsToMany(Discount::class, 'discount_academies', 'academy_id', 'discount_id');
    }
}
