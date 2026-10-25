<?php

namespace App\Models;

use Database\Factories\GalleryAlbumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['title_ar', 'title_en', 'sort_order', 'is_published'])]
class GalleryAlbum extends Model
{
    /** @use HasFactory<GalleryAlbumFactory> */
    use HasFactory;

    /**
     * The table has no updated_at column.
     */
    public const UPDATED_AT = null;

    /**
     * Get the photos in this album.
     *
     * @return HasMany<GalleryPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(GalleryPhoto::class, 'album_id');
    }

    /**
     * Get the first photo of this album by sort order, used as its cover.
     *
     * @return HasOne<GalleryPhoto, $this>
     */
    public function coverPhoto(): HasOne
    {
        return $this->hasOne(GalleryPhoto::class, 'album_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }
}
