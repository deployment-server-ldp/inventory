<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SparePart extends Model
{
    use HasActiveFlag;

    public const TYPE_CNC = 'cnc';

    public const TYPE_IMPORTED = 'imported';

    public const TYPES = [self::TYPE_CNC => 'CNC Manufactured', self::TYPE_IMPORTED => 'Imported'];

    protected $fillable = [
        'inventory_type', 'sku', 'name', 'category_id', 'unit_id', 'specification', 'description',
        'brand', 'part_number', 'supplier_id', 'final_operation_id', 'min_stock', 'unit_cost',
        'currency', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'min_stock' => 'decimal:3',
        'opening_stock' => 'decimal:3',
        'current_stock' => 'decimal:3',
        'unit_cost' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function finalOperation(): BelongsTo
    {
        return $this->belongsTo(Operation::class, 'final_operation_id');
    }

    public function machineryModels(): BelongsToMany
    {
        return $this->belongsToMany(MachineryModel::class, 'spare_part_machinery_model');
    }

    public function images(): HasMany
    {
        return $this->hasMany(SparePartImage::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(SparePartImage::class)->where('is_primary', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cncTransactions(): HasMany
    {
        return $this->hasMany(CncInventoryTransaction::class);
    }

    public function importedTransactions(): HasMany
    {
        return $this->hasMany(ImportedInventoryTransaction::class);
    }

    public function scopeType(Builder $q, string $type): Builder
    {
        return $q->where('spare_parts.inventory_type', $type);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $q->where(function (Builder $w) use ($like) {
            $w->where('spare_parts.name', 'like', $like)
                ->orWhere('spare_parts.sku', 'like', $like)
                ->orWhere('spare_parts.part_number', 'like', $like)
                ->orWhere('spare_parts.brand', 'like', $like)
                ->orWhere('spare_parts.specification', 'like', $like)
                ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like));
        });
    }

    public function scopeLowStock(Builder $q): Builder
    {
        return $q->where('spare_parts.current_stock', '>', 0)
            ->where('spare_parts.min_stock', '>', 0)
            ->whereColumn('spare_parts.current_stock', '<=', 'spare_parts.min_stock');
    }

    public function scopeOutOfStock(Builder $q): Builder
    {
        return $q->where('spare_parts.current_stock', '<=', 0);
    }

    public function isCnc(): bool
    {
        return $this->inventory_type === self::TYPE_CNC;
    }

    public function stockStatus(): string
    {
        $stock = (float) $this->current_stock;
        if ($stock <= 0) {
            return 'out';
        }
        if ((float) $this->min_stock > 0 && $stock <= (float) $this->min_stock) {
            return 'low';
        }

        return 'ok';
    }

    public function thumbUrl(): ?string
    {
        $img = $this->relationLoaded('primaryImage') ? $this->primaryImage : $this->primaryImage()->first();

        return $img ? route('media.part-image', [$img->id, 'thumb']) : null;
    }

    public function imageUrl(): ?string
    {
        $img = $this->relationLoaded('primaryImage') ? $this->primaryImage : $this->primaryImage()->first();

        return $img ? route('media.part-image', [$img->id, 'full']) : null;
    }

    public function routeBase(): string
    {
        return $this->isCnc() ? 'cnc.parts' : 'imported.products';
    }
}
