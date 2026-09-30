<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasActiveFlag;

    protected $fillable = ['name', 'symbol', 'allows_decimal', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'allows_decimal' => 'boolean'];
}
