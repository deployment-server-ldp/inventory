<?php

namespace App\Http\Controllers\Cnc;

use App\Http\Controllers\Controller;
use App\Models\CncProductionCompletion;
use App\Models\CncProductionRecord;
use App\Models\Machine;
use App\Models\Operator;
use App\Models\SparePart;
use App\Support\DateRange;
use App\Support\Trend;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * "Finished parts" = output of each part's FINAL operation. "Operation output" = sum of all operations
 * (work done, not stock). Both are shown and labelled; neither is stock — stock comes only from approved completions.
 */
class CncDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'month');
        $today = now()->toDateString();

        $scoped = function () use ($request) {
            $q = CncProductionRecord::query()->where('cnc_production_records.status', 'completed');
            foreach (['machine_id', 'spare_part_id', 'operator_id'] as $f) {
                if ($v = $request->integer($f)) {
                    $q->where("cnc_production_records.{$f}", $v);
                }
            }

            return $q;
        };
        $finalSum = fn ($q) => (float) $q->where('is_final_operation', true)->sum('quantity');

        $activeMachines = Machine::where('status', 'active')->count();
        $runningMachineIds = CncProductionRecord::where('status', 'running')->distinct()->pluck('machine_id');
        $inRange = $scoped()->whereBetween('production_date', [$range->fromDate(), $range->toDate()]);

        $kpi = [
            'active' => $activeMachines,
            'running' => $runningMachineIds->count(),
            'idle' => max(0, Machine::where('status', 'active')->whereNotIn('id', $runningMachineIds)->count()),
            'today_final' => $finalSum($scoped()->where('production_date', $today)),
            'today_ops' => (float) $scoped()->where('production_date', $today)->sum('quantity'),
            'month_final' => $finalSum($scoped()->whereBetween('production_date', [now()->startOfMonth()->toDateString(), $today])),
            'ytd_final' => $finalSum($scoped()->whereBetween('production_date', [now()->startOfYear()->toDateString(), $today])),
            'in_production' => CncProductionRecord::where('status', 'running')->distinct()->count('spare_part_id'),
            'ops_completed' => (clone $inRange)->count(),
            'range_ops' => (float) (clone $inRange)->sum('quantity'),
            'range_final' => $finalSum(clone $inRange),
            'pending_qc' => CncProductionCompletion::where('status', 'pending')->count(),
            'accepted_range' => (float) CncProductionCompletion::where('status', 'approved')->whereBetween('completion_date', [$range->fromDate(), $range->toDate()])->sum('quantity_accepted'),
        ];

        $byMachine = (clone $inRange)->join('machines', 'machines.id', '=', 'cnc_production_records.machine_id')
            ->groupBy('machines.id', 'machines.code', 'machines.sort_order')->orderBy('machines.sort_order')->orderBy('machines.id')
            ->selectRaw('machines.id, machines.code, SUM(quantity) q, SUM(CASE WHEN is_final_operation=1 THEN quantity ELSE 0 END) f')->get();
        $byOperator = (clone $inRange)->join('operators', 'operators.id', '=', 'cnc_production_records.operator_id')
            ->groupBy('operators.id', 'operators.name')->orderByDesc(DB::raw('SUM(quantity)'))->limit(12)
            ->selectRaw('operators.id, operators.name, SUM(quantity) q')->get();
        $byPart = (clone $inRange)->groupBy('spare_part_id', 'part_sku', 'part_name')->orderByDesc(DB::raw('SUM(CASE WHEN is_final_operation=1 THEN quantity ELSE 0 END)'))->limit(10)
            ->selectRaw('spare_part_id, part_sku, part_name, SUM(quantity) q, SUM(CASE WHEN is_final_operation=1 THEN quantity ELSE 0 END) f')->get();
        $grain = Trend::grain($request->query('grain'), $range);
        $trend = Trend::series($inRange, 'production_date', $range, $grain, [
            'final' => 'SUM(CASE WHEN is_final_operation=1 THEN quantity ELSE 0 END)',
            'ops' => 'SUM(quantity)',
        ]);

        $running = CncProductionRecord::where('status', 'running')->with(['machine', 'operator', 'sparePart.primaryImage'])->orderBy('production_date')->orderBy('start_time')->get();
        $filterQs = array_filter($request->only(['machine_id', 'spare_part_id', 'operator_id'])) + $range->query();

        return view('cnc.dashboard', compact('range', 'kpi', 'byMachine', 'byOperator', 'byPart', 'trend', 'grain', 'running', 'filterQs') + [
            'machines' => Machine::ordered()->get(),
            'operators' => Operator::orderBy('name')->get(),
            'parts' => SparePart::type('cnc')->orderBy('name')->get(['id', 'name', 'sku']),
        ]);
    }
}
