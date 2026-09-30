<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Model;

class Operator extends Model
{
    use HasActiveFlag;

    protected $fillable = ['employee_code', 'name', 'contact', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function getLabelAttribute(): string
    {
        return "{$this->name} ({$this->employee_code})";
    }
}
