<?php

namespace App\Models;

use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'amount', 'method', 'reason', 'provider_reference', 'processed_by_staff_id'])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
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
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function processedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'processed_by_staff_id');
    }
}
