<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CncOperatorWiseReport extends Report
{
    public string $key = 'cnc-operator-wise';
    public string $title = 'Operator-wise production report';
    public string $description = 'Output and machine time per operator.';
    public array $filters = ['machine', 'part_cnc', 'operation'];

    public function columns(): array
    {
        return ['employee_code' => ['Emp. code', 'ref'], 'operator' => ['Operator', 'text'], 'entries' => ['Entries', 'num'], 'machines' => ['Machines used', 'num'],
            'ops_qty' => ['Operation qty', 'num'], 'final_qty' => ['Finished qty', 'num'], 'hours' => ['Hours', 'num']];
    }

    public function query(Request $request, DateRange $range, User $user): Builder
    {
        $q = DB::table('cnc_production_records as r')->join('operators as o', 'o.id', '=', 'r.operator_id')
            ->where('r.status', 'completed')->whereBetween('r.production_date', [$range->fromDate(), $range->toDate()])
            ->groupBy('o.id', 'o.employee_code', 'o.name')
            ->select(['o.employee_code', 'o.name as operator', DB::raw('COUNT(*) as entries'), DB::raw('COUNT(DISTINCT r.machine_id) as machines'), DB::raw('SUM(r.quantity) as ops_qty'),
                DB::raw('SUM(CASE WHEN r.is_final_operation = 1 THEN r.quantity ELSE 0 END) as final_qty'), DB::raw('ROUND(SUM(COALESCE(r.duration_minutes,0))/60, 2) as hours')]);
        self::applyCommon($q, $request, ['machine_id' => 'r.machine_id', 'spare_part_id' => 'r.spare_part_id', 'operation_id' => 'r.operation_id']);

        return $q;
    }

    public function searchable(): array
    {
        return ['o.name', 'o.employee_code'];
    }

    public function defaultSort(): array
    {
        return ['ops_qty', 'desc'];
    }

    public function totals(): array
    {
        return ['entries', 'ops_qty', 'final_qty', 'hours'];
    }
}
