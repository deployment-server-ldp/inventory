<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CncProductionDailyReport extends Report
{
    public string $key = 'cnc-production-daily';

    public string $title = 'Daily CNC production report';

    public string $description = 'Every production entry with machine, part, operation, times, quantity and operator.';

    public array $filters = ['machine', 'part_cnc', 'operator', 'operation', 'txn_status'];

    public function columns(): array
    {
        return [
            'production_date' => ['Date', 'date'], 'reference_no' => ['Reference', 'ref'], 'machine' => ['Machine', 'text'],
            'part_sku' => ['SKU', 'ref'], 'part_name' => ['Part', 'text'], 'operation_name' => ['Operation', 'text'], 'final' => ['Final op', 'text'],
            'target_model' => ['Target model', 'text'], 'start_time' => ['Start', 'text'], 'end_time' => ['End', 'text'],
            'duration_minutes' => ['Minutes', 'num'], 'quantity' => ['Qty', 'num'], 'operator' => ['Operator', 'text'], 'status' => ['Status', 'text'],
        ];
    }

    public function query(Request $request, DateRange $range, User $user): Builder
    {
        $q = DB::table('cnc_production_records as r')
            ->join('machines as m', 'm.id', '=', 'r.machine_id')
            ->join('operators as o', 'o.id', '=', 'r.operator_id')
            ->leftJoin('machinery_models as mm', 'mm.id', '=', 'r.machinery_model_id')
            ->whereBetween('r.production_date', [$range->fromDate(), $range->toDate()])
            ->select(['r.production_date', 'r.reference_no', 'm.code as machine', 'r.part_sku', 'r.part_name', 'r.operation_name',
                DB::raw("CASE WHEN r.is_final_operation = 1 THEN 'Yes' ELSE '' END as final"), 'mm.name as target_model',
                DB::raw('TIME_FORMAT(r.start_time, "%H:%i") as start_time'), DB::raw('TIME_FORMAT(r.end_time, "%H:%i") as end_time'),
                'r.duration_minutes', 'r.quantity', 'o.name as operator', 'r.status']);
        self::applyCommon($q, $request, ['machine_id' => 'r.machine_id', 'spare_part_id' => 'r.spare_part_id', 'operator_id' => 'r.operator_id', 'operation_id' => 'r.operation_id']);
        $status = $request->query('status');
        in_array($status, ['running', 'completed', 'cancelled'], true) ? $q->where('r.status', $status) : $q->where('r.status', '!=', 'cancelled');

        return $q;
    }

    public function searchable(): array
    {
        return ['r.reference_no', 'r.part_sku', 'r.part_name', 'o.name', 'm.code'];
    }

    public function defaultSort(): array
    {
        return ['production_date', 'desc'];
    }

    public function totals(): array
    {
        return ['duration_minutes', 'quantity'];
    }
}
