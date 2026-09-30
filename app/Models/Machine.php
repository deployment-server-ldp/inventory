<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Machine extends Model
{
    public const STATUSES = ['active' => 'Active', 'maintenance' => 'Under maintenance', 'inactive' => 'Inactive'];

    protected $fillable = ['code', 'name', 'status', 'description', 'sort_order'];

    public function productionRecords(): HasMany
    {
        return $this->hasMany(CncProductionRecord::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    public function getLabelAttribute(): string
    {
        return $this->code === $this->name ? $this->code : "{$this->code} — {$this->name}";
    }
}
