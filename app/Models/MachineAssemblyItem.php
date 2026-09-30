<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MachineAssemblyItem extends Model
{
    protected $fillable = ['machine_assembly_id', 'spare_part_id', 'planned_quantity', 'remarks'];

    protected $casts = ['planned_quantity' => 'decimal:3'];

    public function assembly(): BelongsTo
    {
        return $this->belongsTo(MachineAssembly::class, 'machine_assembly_id');
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }
}
