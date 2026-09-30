<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockValuationReport extends Report
{
    public string $key = 'stock-valuation';
    public string $title = 'Stock valuation report';
    public string $description = 'Value of imported stock at the recorded unit cost. Only products with a cost are included; values are per currency.';
    public string $group = 'imported';
    public bool $usesDates = false;
    public array $filters = ['category_imported'];

    public function columns(): array
    {
        return ['sku' => ['SKU', 'ref'], 'name' => ['Product', 'text'], 'category' => ['Category', 'text'], 'stock' => ['Stock', 'num'], 'unit' => ['Unit', 'text'],
            'unit_cost' => ['Unit cost', 'num'], 'currency' => ['Currency', 'text'], 'value' => ['Stock value', 'num']];
    }

    public function query(Request $request, DateRange $range, User $user): Builder
    {
        $q = DB::table('spare_parts as p')->join('part_categories as c', 'c.id', '=', 'p.category_id')->join('units as u', 'u.id', '=', 'p.unit_id')
            ->where('p.inventory_type', 'imported')->where('p.is_active', true)->whereNotNull('p.unit_cost')
            ->select(['p.sku', 'p.name', 'c.name as category', 'p.current_stock as stock', 'u.symbol as unit', 'p.unit_cost', 'p.currency', DB::raw('ROUND(p.current_stock * p.unit_cost, 2) as value')]);
        if ($c = $request->integer('category_id')) {
            $q->where('p.category_id', $c);
        }

        return $q;
    }

    public function searchable(): array
    {
        return ['p.sku', 'p.name', 'c.name'];
    }

    public function defaultSort(): array
    {
        return ['value', 'desc'];
    }

    public function totals(): array
    {
        return ['stock'];
    }
}
