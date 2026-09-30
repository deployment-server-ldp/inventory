<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CncProductionCompletion extends Model
{
    public const STATUSES = ['pending' => 'Pending approval', 'approved' => 'Approved', 'rejected' => 'Rejected', 'reversed' => 'Reversed'];

    protected $fillable = [
        'reference_no', 'completion_date', 'spare_part_id', 'part_name', 'part_sku', 'quantity_inspected',
        'quantity_accepted', 'quantity_rejected', 'status', 'remarks', 'submitted_by', 'approved_by',
        'approved_at', 'decision_notes', 'reversed_by', 'reversed_at', 'reversal_reason', 'idempotency_key',
    ];

    protected $casts = [
        'completion_date' => 'date',
        'quantity_inspected' => 'decimal:3',
        'quantity_accepted' => 'decimal:3',
        'quantity_rejected' => 'decimal:3',
        'approved_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(CncInventoryTransaction::class, 'completion_id')->where('type', 'production_receipt');
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            default => 'secondary',
        };
    }
}
