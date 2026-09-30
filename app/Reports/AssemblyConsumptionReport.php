<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssemblyConsumptionReport extends Report
{
    public string $key = 'assembly-consumption';

    public string $title = 'Machine assembly material consumption report';

    public string $description = 'Planned vs actually issued imported parts per assembly (actual = net OUT transactions tagged with the assembly).';

    public string $group = 'imported';

    public bool $usesDates = false;

    public array $filters = ['assembly', 'assembly_status'];

    public function columns(): array
    {
        return ['assembly' => ['Assembly', 'ref'], 'assembly_name' => ['Machine / project', 'text'], 'status' => ['Status', 'text'], 'part_sku' => ['SKU', 'ref'],
            'part_name' => ['Product', 'text'], 'planned' => ['Planned', 'num'], 'issued' => ['Issued', 'num'], 'remaining' => ['Remaining', 'num'], 'available' => ['In stock now', 'num']];
    }

    public function query(Request $request, DateRange $range, User $user): Builder
    {
        $issued = DB::table('imported_inventory_transactions')->where('type', 'out')->where('is_reversed', false)->whereNotNull('machine_assembly_id')
            ->groupBy('machine_assembly_id', 'spare_part_id')->select(['machine_assembly_id', 'spare_part_id', DB::raw('SUM(quantity_out) as qty')]);
        $q = DB::table('machine_assembly_items as i')->join('machine_assemblies as a', 'a.id', '=', 'i.machine_assembly_id')->join('spare_parts as p', 'p.id', '=', 'i.spare_part_id')
            ->leftJoinSub($issued, 'x', fn ($j) => $j->on('x.machine_assembly_id', '=', 'i.machine_assembly_id')->on('x.spare_part_id', '=', 'i.spare_part_id'))
            ->select(['a.reference_no as assembly', 'a.name as assembly_name', 'a.status', 'p.sku as part_sku', 'p.name as part_name', 'i.planned_quantity as planned',
                DB::raw('COALESCE(x.qty,0) as issued'), DB::raw('GREATEST(i.planned_quantity - COALESCE(x.qty,0), 0) as remaining'), 'p.current_stock as available']);
        self::applyCommon($q, $request, ['machine_assembly_id' => 'a.id']);
        if ($s = $request->query('assembly_status')) {
            $q->where('a.status', $s);
        }

        return $q;
    }

    public function searchable(): array
    {
        return ['a.reference_no', 'a.name', 'p.sku', 'p.name'];
    }

    public function defaultSort(): array
    {
        return ['assembly', 'asc'];
    }

    public function totals(): array
    {
        return ['planned', 'issued', 'remaining'];
    }
}
