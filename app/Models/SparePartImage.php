<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SparePartImage extends Model
{
    protected $fillable = [
        'spare_part_id', 'path', 'thumb_path', 'original_name', 'mime', 'size', 'is_primary', 'sort_order', 'uploaded_by',
    ];

    protected $casts = ['is_primary' => 'boolean'];

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function url(string $variant = 'full'): string
    {
        return route('media.part-image', [$this->id, $variant]);
    }
}
