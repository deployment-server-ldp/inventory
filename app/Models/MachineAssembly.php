<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MachineAssembly extends Model
{
    public const STATUSES = ['planned' => 'Planned', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    protected $fillable = [
        'reference_no', 'name', 'machinery_model_id', 'customer', 'status', 'start_date', 'target_date',
        'completed_at', 'remarks', 'created_by', 'updated_by',
    ];

    protected $casts = ['start_date' => 'date', 'target_date' => 'date', 'completed_at' => 'datetime'];

    public function machineryModel(): BelongsTo
    {
        return $this->belongsTo(MachineryModel::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MachineAssemblyItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ImportedInventoryTransaction::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['planned', 'in_progress'], true);
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'planned' => 'info',
            'in_progress' => 'primary',
            'completed' => 'success',
            default => 'secondary',
        };
    }
}
