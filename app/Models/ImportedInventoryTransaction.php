<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ImportedInventoryTransaction extends Model
{
    public const TYPES = [
        'opening' => 'Opening stock',
        'in' => 'Inventory IN',
        'out' => 'Inventory OUT',
        'adjustment_in' => 'Adjustment (+)',
        'adjustment_out' => 'Adjustment (−)',
        'reversal' => 'Reversal',
    ];

    protected $fillable = [
        'reference_no', 'transaction_date', 'spare_part_id', 'part_name', 'part_sku', 'category_name', 'unit_name',
        'specification', 'type', 'quantity_in', 'quantity_out', 'balance_after', 'supplier_id', 'source',
        'document_reference', 'unit_cost', 'currency', 'machinery_model_id', 'purpose', 'collected_by', 'department',
        'machine_assembly_id', 'stock_adjustment_id', 'reversal_of_id', 'is_reversed', 'remarks', 'idempotency_key',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'quantity_in' => 'decimal:3',
        'quantity_out' => 'decimal:3',
        'balance_after' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'is_reversed' => 'boolean',
    ];

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function machineryModel(): BelongsTo
    {
        return $this->belongsTo(MachineryModel::class);
    }

    public function assembly(): BelongsTo
    {
        return $this->belongsTo(MachineAssembly::class, 'machine_assembly_id');
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
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

    public function typeBadge(): string
    {
        return match ($this->type) {
            'in', 'adjustment_in', 'opening' => 'success',
            'out', 'adjustment_out' => 'danger',
            default => 'secondary',
        };
    }

    public function isReversible(): bool
    {
        return in_array($this->type, ['in', 'out'], true) && ! $this->is_reversed;
    }
}
