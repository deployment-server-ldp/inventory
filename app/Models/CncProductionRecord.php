<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CncProductionRecord extends Model
{
    public const STATUSES = ['running' => 'Running', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    protected $fillable = [
        'reference_no', 'production_date', 'machine_id', 'spare_part_id', 'part_name', 'part_sku',
        'machinery_model_id', 'operation_id', 'operation_name', 'operation_sequence', 'is_final_operation',
        'start_time', 'end_time', 'duration_minutes', 'quantity', 'operator_id', 'remarks', 'status',
        'cancelled_at', 'cancelled_by', 'cancel_reason', 'idempotency_key', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'production_date' => 'date',
        'quantity' => 'decimal:3',
        'is_final_operation' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function machineryModel(): BelongsTo
    {
        return $this->belongsTo(MachineryModel::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** Records that count as real output (not cancelled). */
    public function scopeEffective(Builder $q): Builder
    {
        return $q->where('cnc_production_records.status', '!=', 'cancelled');
    }

    public function durationLabel(): string
    {
        if ($this->duration_minutes === null) {
            return '—';
        }
        $h = intdiv($this->duration_minutes, 60);
        $m = $this->duration_minutes % 60;

        return ($h ? $h.'h ' : '').$m.'m';
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'running' => 'primary',
            'completed' => 'success',
            default => 'secondary',
        };
    }
}
