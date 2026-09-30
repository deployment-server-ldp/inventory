<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CncProductionMonthlyReport extends Report
{
    public string $key = 'cnc-production-monthly';

    public string $title = 'Monthly CNC production report';

    public string $description = 'Production per month and machine: entries, operation output, finished (final-operation) output and machine hours.';

    public array $filters = ['machine', 'part_cnc', 'operator'];

    public function columns(): array
    {
        return ['month' => ['Month', 'text'], 'machine' => ['Machine', 'text'], 'entries' => ['Entries', 'num'], 'parts' => ['Distinct parts', 'num'],
            'ops_qty' => ['Operation qty', 'num'], 'final_qty' => ['Finished qty (final op)', 'num'], 'hours' => ['Machine hours', 'num']];
    }

    public function query(Request $request, DateRange $range, User $user): Builder
    {
        $q = DB::table('cnc_production_records as r')->join('machines as m', 'm.id', '=', 'r.machine_id')
            ->where('r.status', 'completed')->whereBetween('r.production_date', [$range->fromDate(), $range->toDate()])
            ->groupBy(DB::raw("DATE_FORMAT(r.production_date, '%Y-%m')"), 'm.id', 'm.code')
            ->select([DB::raw("DATE_FORMAT(r.production_date, '%Y-%m') as month"), 'm.code as machine', DB::raw('COUNT(*) as entries'),
                DB::raw('COUNT(DISTINCT r.spare_part_id) as parts'), DB::raw('SUM(r.quantity) as ops_qty'),
                DB::raw('SUM(CASE WHEN r.is_final_operation = 1 THEN r.quantity ELSE 0 END) as final_qty'), DB::raw('ROUND(SUM(COALESCE(r.duration_minutes,0))/60, 2) as hours')]);
        self::applyCommon($q, $request, ['machine_id' => 'r.machine_id', 'spare_part_id' => 'r.spare_part_id', 'operator_id' => 'r.operator_id']);

        return $q;
    }

    public function searchable(): array
    {
        return ['m.code'];
    }

    public function defaultSort(): array
    {
        return ['month', 'desc'];
    }

    public function totals(): array
    {
        return ['entries', 'ops_qty', 'final_qty', 'hours'];
    }
}
