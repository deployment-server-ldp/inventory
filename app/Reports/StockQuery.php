<?php

namespace App\Reports;

use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockQuery
{
    public static function build(string $type, string $ledger, Request $request, DateRange $range): Builder
    {
        $mov = DB::table($ledger)->whereBetween('transaction_date', [$range->fromDate(), $range->toDate()])->where('type', '!=', 'opening')
            ->groupBy('spare_part_id')->select(['spare_part_id', DB::raw('SUM(quantity_in) as qin'), DB::raw('SUM(quantity_out) as qout')]);
        $q = DB::table('spare_parts as p')->join('part_categories as c', 'c.id', '=', 'p.category_id')->join('units as u', 'u.id', '=', 'p.unit_id')
            ->leftJoinSub($mov, 'mv', 'mv.spare_part_id', '=', 'p.id')
            ->where('p.inventory_type', $type)
            ->select(['p.sku', 'p.name', 'c.name as category', 'u.symbol as unit', 'p.min_stock', DB::raw('COALESCE(mv.qin,0) as received'), DB::raw('COALESCE(mv.qout,0) as issued'),
                'p.current_stock as stock', DB::raw("CASE WHEN p.current_stock <= 0 THEN 'Out of stock' WHEN p.min_stock > 0 AND p.current_stock <= p.min_stock THEN 'Low stock' ELSE 'OK' END as status")]);
        if ($c = $request->integer('category_id')) {
            $q->where('p.category_id', $c);
        }
        match ($request->query('stock')) {
            'low' => $q->where('p.current_stock', '>', 0)->where('p.min_stock', '>', 0)->whereColumn('p.current_stock', '<=', 'p.min_stock'),
            'out' => $q->where('p.current_stock', '<=', 0),
            'in' => $q->where('p.current_stock', '>', 0),
            default => null,
        };
        if ($request->query('active', '1') === '1') {
            $q->where('p.is_active', true);
        }

        return $q;
    }
}
