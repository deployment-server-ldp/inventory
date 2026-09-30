<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CncMachineWiseReport extends Report
{
    public string $key = 'cnc-machine-wise';
    public string $title = 'Machine-wise production report';
    public string $description = 'Totals per CNC machine for the selected period.';
    public array $filters = ['part_cnc', 'operator', 'operation'];

    public function columns(): array
    {
        return ['machine' => ['Machine', 'text'], 'machine_name' => ['Name', 'text'], 'status' => ['Status', 'text'], 'entries' => ['Entries', 'num'],
            'parts' => ['Distinct parts', 'num'], 'ops_qty' => ['Operation qty', 'num'], 'final_qty' => ['Finished qty', 'num'], 'hours' => ['Hours', 'num']];
    }

    public function query(Request $request, DateRange $range, User $user): Builder
    {
        $sub = DB::table('cnc_production_records as r')->where('r.status', 'completed')->whereBetween('r.production_date', [$range->fromDate(), $range->toDate()]);
        self::applyCommon($sub, $request, ['spare_part_id' => 'r.spare_part_id', 'operator_id' => 'r.operator_id', 'operation_id' => 'r.operation_id']);
        $sub->groupBy('r.machine_id')->select(['r.machine_id', DB::raw('COUNT(*) as entries'), DB::raw('COUNT(DISTINCT r.spare_part_id) as parts'), DB::raw('SUM(r.quantity) as ops_qty'),
            DB::raw('SUM(CASE WHEN r.is_final_operation = 1 THEN r.quantity ELSE 0 END) as final_qty'), DB::raw('ROUND(SUM(COALESCE(r.duration_minutes,0))/60, 2) as hours')]);

        return DB::table('machines as m')->leftJoinSub($sub, 's', 's.machine_id', '=', 'm.id')
            ->select(['m.code as machine', 'm.name as machine_name', 'm.status', DB::raw('COALESCE(s.entries,0) as entries'), DB::raw('COALESCE(s.parts,0) as parts'),
                DB::raw('COALESCE(s.ops_qty,0) as ops_qty'), DB::raw('COALESCE(s.final_qty,0) as final_qty'), DB::raw('COALESCE(s.hours,0) as hours'), 'm.sort_order']);
    }

    public function searchable(): array
    {
        return ['m.code', 'm.name'];
    }

    public function defaultSort(): array
    {
        return ['machine', 'asc'];
    }

    public function totals(): array
    {
        return ['entries', 'ops_qty', 'final_qty', 'hours'];
    }
}
