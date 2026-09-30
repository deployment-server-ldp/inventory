<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryStockReport extends Report
{
    public string $key = 'category-stock';
    public string $title = 'Category-wise stock report';
    public string $description = 'Number of parts and units in stock per category, separately for each inventory.';
    public string $group = 'both';
    public bool $usesDates = false;
    public array $filters = ['inventory_type'];

    public function columns(): array
    {
        return ['inventory' => ['Inventory', 'text'], 'category' => ['Category', 'text'], 'parts' => ['Parts', 'num'], 'units' => ['Units in stock', 'num'],
            'low' => ['Low stock', 'num'], 'out_of_stock' => ['Out of stock', 'num']];
    }

    public function query(Request $request, DateRange $range, User $user): Builder
    {
        return DB::table('spare_parts as p')->join('part_categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.is_active', true)->whereIn('p.inventory_type', $this->allowedTypes($user, $request))
            ->groupBy('p.inventory_type', 'c.id', 'c.name')
            ->select([DB::raw("CASE WHEN p.inventory_type='cnc' THEN 'CNC' ELSE 'Imported' END as inventory"), 'c.name as category', DB::raw('COUNT(*) as parts'), DB::raw('SUM(p.current_stock) as units'),
                DB::raw('SUM(CASE WHEN p.current_stock > 0 AND p.min_stock > 0 AND p.current_stock <= p.min_stock THEN 1 ELSE 0 END) as low'),
                DB::raw('SUM(CASE WHEN p.current_stock <= 0 THEN 1 ELSE 0 END) as out_of_stock')]);
    }

    public function searchable(): array
    {
        return ['c.name'];
    }

    public function totals(): array
    {
        return ['parts', 'low', 'out_of_stock'];
    }
}
