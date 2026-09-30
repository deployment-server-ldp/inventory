<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CncInventoryTransaction extends Model
{
    public const TYPES = [
        'opening' => 'Opening stock',
        'production_receipt' => 'Production receipt',
        'issue' => 'Issue',
        'adjustment_in' => 'Adjustment (+)',
        'adjustment_out' => 'Adjustment (−)',
        'reversal' => 'Reversal',
    ];

    protected $fillable = [
        'reference_no', 'transaction_date', 'spare_part_id', 'part_name', 'part_sku', 'unit_name', 'type',
        'quantity_in', 'quantity_out', 'balance_after', 'completion_id', 'stock_adjustment_id', 'reversal_of_id',
        'is_reversed', 'issued_to', 'machinery_model_id', 'purpose', 'remarks', 'idempotency_key', 'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'quantity_in' => 'decimal:3',
        'quantity_out' => 'decimal:3',
        'balance_after' => 'decimal:3',
        'is_reversed' => 'boolean',
    ];

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function completion(): BelongsTo
    {
        return $this->belongsTo(CncProductionCompletion::class, 'completion_id');
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function machineryModel(): BelongsTo
    {
        return $this->belongsTo(MachineryModel::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
