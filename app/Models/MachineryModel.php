<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MachineryModel extends Model
{
    use HasActiveFlag;

    protected $fillable = ['name', 'manufacturer', 'model_code', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function spareParts(): BelongsToMany
    {
        return $this->belongsToMany(SparePart::class, 'spare_part_machinery_model');
    }
}
