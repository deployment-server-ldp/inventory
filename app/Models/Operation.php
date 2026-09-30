<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Operation extends Model
{
    use HasActiveFlag;

    protected $fillable = ['name', 'sequence', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sequence' => 'integer'];

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sequence');
    }
}
