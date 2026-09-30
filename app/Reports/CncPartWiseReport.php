<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CncPartWiseReport extends Report
{
    public string $key = 'cnc-part-wise';

    public string $title = 'Part-wise production report';

    public string $description = 'Per part: operation output, finished output, QC accepted / rejected in the period, and current stock.';

    public array $filters = ['machine', 'operator', 'category_cnc'];

    public function columns(): array
    {
        return ['sku' => ['SKU', 'ref'], 'name' => ['Part', 'text'], 'category' => ['Category', 'text'], 'entries' => ['Entries', 'num'],
            'ops_qty' => ['Operation qty', 'num'], 'final_qty' => ['Finished qty', 'num'], 'accepted' => ['QC accepted', 'num'], 'rejected' => ['QC rejected', 'num'], 'stock' => ['Current stock', 'num']];
    }

    public function query(Request $request, DateRange $range, User $user): Builder
    {
        $prod = DB::table('cnc_production_records as r')->where('r.status', 'completed')->whereBetween('r.production_date', [$range->fromDate(), $range->toDate()]);
        self::applyCommon($prod, $request, ['machine_id' => 'r.machine_id', 'operator_id' => 'r.operator_id']);
        $prod->groupBy('r.spare_part_id')->select(['r.spare_part_id', DB::raw('COUNT(*) as entries'), DB::raw('SUM(r.quantity) as ops_qty'), DB::raw('SUM(CASE WHEN r.is_final_operation = 1 THEN r.quantity ELSE 0 END) as final_qty')]);
        $comp = DB::table('cnc_production_completions')->where('status', 'approved')->whereBetween('completion_date', [$range->fromDate(), $range->toDate()])
            ->groupBy('spare_part_id')->select(['spare_part_id', DB::raw('SUM(quantity_accepted) as accepted'), DB::raw('SUM(quantity_rejected) as rejected')]);

        $q = DB::table('spare_parts as p')->join('part_categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoinSub($prod, 'pr', 'pr.spare_part_id', '=', 'p.id')->leftJoinSub($comp, 'cp', 'cp.spare_part_id', '=', 'p.id')
            ->where('p.inventory_type', 'cnc')->where(fn ($w) => $w->whereNotNull('pr.spare_part_id')->orWhereNotNull('cp.spare_part_id'))
            ->select(['p.sku', 'p.name', 'c.name as category', DB::raw('COALESCE(pr.entries,0) as entries'), DB::raw('COALESCE(pr.ops_qty,0) as ops_qty'),
                DB::raw('COALESCE(pr.final_qty,0) as final_qty'), DB::raw('COALESCE(cp.accepted,0) as accepted'), DB::raw('COALESCE(cp.rejected,0) as rejected'), 'p.current_stock as stock']);
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
        return ['final_qty', 'desc'];
    }

    public function totals(): array
    {
        return ['entries', 'ops_qty', 'final_qty', 'accepted', 'rejected'];
    }
}
