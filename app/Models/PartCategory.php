<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartCategory extends Model
{
    use HasActiveFlag;

    public const SCOPES = ['both' => 'Both inventories', 'cnc' => 'CNC only', 'imported' => 'Imported only'];

    protected $fillable = ['name', 'scope', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class, 'category_id');
    }

    public function scopeForType(Builder $q, string $type): Builder
    {
        return $q->whereIn('scope', [$type, 'both']);
    }
}
