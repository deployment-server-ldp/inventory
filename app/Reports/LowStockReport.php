<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LowStockReport extends Report
{
    public string $key = 'low-stock';

    public string $title = 'Low-stock and out-of-stock report';

    public string $description = 'Active parts at or below their minimum level, or with zero stock.';

    public string $group = 'both';

    public bool $usesDates = false;

    public array $filters = ['inventory_type', 'stock_alert'];

    public function columns(): array
    {
        return ['inventory' => ['Inventory', 'text'], 'sku' => ['SKU', 'ref'], 'name' => ['Part', 'text'], 'category' => ['Category', 'text'], 'unit' => ['Unit', 'text'],
            'min_stock' => ['Min level', 'num'], 'stock' => ['Current stock', 'num'], 'shortfall' => ['Shortfall', 'num'], 'status' => ['Status', 'text']];
    }

    public function query(Request $request, DateRange $range, User $user): Builder
    {
        $q = DB::table('spare_parts as p')->join('part_categories as c', 'c.id', '=', 'p.category_id')->join('units as u', 'u.id', '=', 'p.unit_id')
            ->where('p.is_active', true)->whereIn('p.inventory_type', $this->allowedTypes($user, $request))
            ->select([DB::raw("CASE WHEN p.inventory_type='cnc' THEN 'CNC' ELSE 'Imported' END as inventory"), 'p.sku', 'p.name', 'c.name as category', 'u.symbol as unit', 'p.min_stock',
                'p.current_stock as stock', DB::raw('GREATEST(p.min_stock - p.current_stock, 0) as shortfall'),
                DB::raw("CASE WHEN p.current_stock <= 0 THEN 'Out of stock' ELSE 'Low stock' END as status")]);
        match ($request->query('alert')) {
            'out' => $q->where('p.current_stock', '<=', 0),
            'low' => $q->where('p.current_stock', '>', 0)->where('p.min_stock', '>', 0)->whereColumn('p.current_stock', '<=', 'p.min_stock'),
            default => $q->where(fn ($w) => $w->where('p.current_stock', '<=', 0)->orWhere(fn ($x) => $x->where('p.min_stock', '>', 0)->whereColumn('p.current_stock', '<=', 'p.min_stock'))),
        };

        return $q;
    }

    public function searchable(): array
    {
        return ['p.sku', 'p.name', 'c.name'];
    }

    public function defaultSort(): array
    {
        return ['shortfall', 'desc'];
    }
}
