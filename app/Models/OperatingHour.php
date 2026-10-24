<?php

namespace App\Models;

use Database\Factories\OperatingHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facility_id', 'weekday', 'open_time', 'close_time'])]
class OperatingHour extends Model
{
    /** @use HasFactory<OperatingHourFactory> */
    use HasFactory;

    /**
     * The table has no timestamp columns.
     */
    public $timestamps = false;

    /**
     * Get the facility these operating hours belong to.
     *
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }
}
