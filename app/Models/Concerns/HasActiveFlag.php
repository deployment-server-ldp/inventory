<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasActiveFlag
{
    public function scopeActive(Builder $q): Builder
    {
        return $q->where($this->getTable().'.is_active', true);
    }
}
