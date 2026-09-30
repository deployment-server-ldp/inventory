<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MachineConsumptionReport extends Report
{
    public string $key = 'machine-consumption';

    public string $title = 'Machine-wise spare parts consumption report';

    public string $description = 'Net imported parts issued per machinery model (reversed issues excluded).';

    public string $group = 'imported';

    public array $filters = ['model', 'part_imported', 'category_imported'];

    public function columns(): array
    {
        return ['machine' => ['Machinery model / purpose', 'text'], 'part_sku' => ['SKU', 'ref'], 'part_name' => ['Product', 'text'], 'category' => ['Category', 'text'],
            'issues' => ['Issues', 'num'], 'quantity' => ['Qty consumed', 'num'], 'unit' => ['Unit', 'text'], 'value' => ['Value at current cost', 'num'], 'currency' => ['Cur.', 'text']];
    }

    public function query(Request $request, DateRange $range, User $user): Builder
    {
        $q = DB::table('imported_inventory_transactions as t')->join('spare_parts as p', 'p.id', '=', 't.spare_part_id')->join('part_categories as c', 'c.id', '=', 'p.category_id')
            ->join('units as u', 'u.id', '=', 'p.unit_id')->leftJoin('machinery_models as mm', 'mm.id', '=', 't.machinery_model_id')
            ->where('t.type', 'out')->where('t.is_reversed', false)->whereBetween('t.transaction_date', [$range->fromDate(), $range->toDate()])
            ->groupBy(DB::raw("COALESCE(mm.name, t.purpose, 'Unspecified')"), 't.spare_part_id', 't.part_sku', 't.part_name', 'c.name', 'u.symbol', 'p.unit_cost', 'p.currency')
            ->select([DB::raw("COALESCE(mm.name, t.purpose, 'Unspecified') as machine"), 't.part_sku', 't.part_name', 'c.name as category', DB::raw('COUNT(*) as issues'),
                DB::raw('SUM(t.quantity_out) as quantity'), 'u.symbol as unit', DB::raw('ROUND(SUM(t.quantity_out) * p.unit_cost, 2) as value'), 'p.currency']);
        self::applyCommon($q, $request, ['machinery_model_id' => 't.machinery_model_id', 'spare_part_id' => 't.spare_part_id', 'category_id' => 'p.category_id']);

        return $q;
    }

    public function searchable(): array
    {
        return ['mm.name', 't.purpose', 't.part_sku', 't.part_name'];
    }

    public function defaultSort(): array
    {
        return ['machine', 'asc'];
    }

    public function totals(): array
    {
        return ['issues', 'quantity'];
    }
}
