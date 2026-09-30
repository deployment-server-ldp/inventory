<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    protected $fillable = [
        'reference_no', 'inventory_type', 'spare_part_id', 'adjustment_date', 'direction', 'quantity', 'reason',
        'remarks', 'created_by',
    ];

    protected $casts = ['adjustment_date' => 'date', 'quantity' => 'decimal:3'];

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
