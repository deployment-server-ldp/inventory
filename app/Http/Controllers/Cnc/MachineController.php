<?php

namespace App\Http\Controllers\Cnc;

use App\Http\Controllers\Controller;
use App\Models\CncProductionRecord;
use App\Models\Machine;
use App\Models\Operation;
use App\Models\Operator;
use App\Models\SparePart;
use App\Services\ExportService;
use App\Support\DateRange;
use App\Support\Qty;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MachineController extends Controller
{
    public function index(Request $request): View
    {
        $today = now()->toDateString();
        $range = DateRange::fromRequest($request, 'month');
        $machines = Machine::ordered()->get();
        $running = CncProductionRecord::where('status', 'running')->with('sparePart.primaryImage')->get()->groupBy('machine_id');
        $todayQty = CncProductionRecord::where('status', 'completed')->where('production_date', $today)->groupBy('machine_id')->selectRaw('machine_id, SUM(quantity) q')->pluck('q', 'machine_id');
        $rangeQty = CncProductionRecord::where('status', 'completed')->whereBetween('production_date', [$range->fromDate(), $range->toDate()])
            ->groupBy('machine_id')->selectRaw('machine_id, SUM(quantity) q, COUNT(*) n, SUM(COALESCE(duration_minutes,0)) m')->get()->keyBy('machine_id');

        return view('cnc.machines.index', compact('machines', 'running', 'todayQty', 'rangeQty', 'range'));
    }

    public function show(Request $request, Machine $machine): View
    {
        $range = DateRange::fromRequest($request, 'month');
        $request->merge(['machine_id' => $machine->id]);
        $base = ProductionController::filteredQuery($request, $range);
        $done = (clone $base)->where('status', 'completed');

        $stats = [
            'today' => (float) CncProductionRecord::where('machine_id', $machine->id)->where('status', 'completed')->where('production_date', now()->toDateString())->sum('quantity'),
            'month' => (float) CncProductionRecord::where('machine_id', $machine->id)->where('status', 'completed')->whereBetween('production_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])->sum('quantity'),
            'range' => (float) (clone $done)->sum('quantity'),
            'minutes' => (int) (clone $done)->sum('duration_minutes'),
            'entries' => (clone $base)->count(),
        ];
        $running = CncProductionRecord::where('machine_id', $machine->id)->where('status', 'running')->with('sparePart.primaryImage', 'operator')->get();
        $byOperation = (clone $done)->groupBy('operation_name', 'operation_sequence')->orderBy('operation_sequence')->selectRaw('operation_name, operation_sequence, SUM(quantity) q, COUNT(*) n, SUM(COALESCE(duration_minutes,0)) m')->get();
        $byPart = (clone $done)->groupBy('spare_part_id', 'part_name', 'part_sku')->orderByDesc(DB::raw('SUM(quantity)'))->selectRaw('spare_part_id, part_name, part_sku, SUM(quantity) q, COUNT(*) n, SUM(COALESCE(duration_minutes,0)) m')->limit(15)->get();
        $byOperator = (clone $done)->join('operators', 'operators.id', '=', 'cnc_production_records.operator_id')
            ->groupBy('operators.id', 'operators.name')->orderByDesc(DB::raw('SUM(quantity)'))->selectRaw('operators.name, SUM(quantity) q, COUNT(*) n, SUM(COALESCE(duration_minutes,0)) m')->get();
        $daily = (clone $done)->groupBy('production_date')->orderBy('production_date')->selectRaw('production_date d, SUM(quantity) q')->pluck('q', 'd');

        $records = (clone $base)->with(['operator', 'sparePart.primaryImage'])->orderByDesc('production_date')->orderByDesc('id')->paginate($this->perPage($request))->withQueryString();

        return view('cnc.machines.show', [
            'machine' => $machine, 'range' => $range, 'stats' => $stats, 'running' => $running, 'byOperation' => $byOperation,
            'byPart' => $byPart, 'byOperator' => $byOperator, 'daily' => $daily, 'records' => $records,
            'parts' => SparePart::type('cnc')->orderBy('name')->get(['id', 'name', 'sku']), 'operators' => Operator::orderBy('name')->get(), 'operations' => Operation::ordered()->get(),
        ]);
    }

    /** Monthly production sheet: one row per day, one column per operation, with totals. */
    public function monthly(Request $request, Machine $machine, ExportService $export): View|Response
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->endOfMonth();
        $sheet = self::monthlySheet($machine, $start, $end);

        $format = $request->query('format');
        if (in_array($format, ['xlsx', 'csv', 'pdf'], true)) {
            $headings = array_merge(['Date', 'Day'], array_map(fn ($o) => $o->name, $sheet['operations']), ['Total qty', 'Entries', 'Run time (h)', 'Parts produced']);
            $rows = [];
            foreach ($sheet['days'] as $d) {
                $rows[] = array_merge([$d['date'], $d['day']], array_map(fn ($o) => $d['ops'][$o->id] ?? 0, $sheet['operations']), [$d['total'], $d['entries'], round($d['minutes'] / 60, 2), $d['parts']]);
            }
            $rows[] = array_merge(['TOTAL', ''], array_map(fn ($o) => $sheet['opTotals'][$o->id] ?? 0, $sheet['operations']), [$sheet['total'], $sheet['entries'], round($sheet['minutes'] / 60, 2), '']);

            return $export->download($format, "Monthly production sheet — {$machine->code} — ".$start->format('F Y'), $headings, $rows, "machine-{$machine->code}-{$month}", ['Machine' => $machine->label, 'Month' => $start->format('F Y')]);
        }

        return view('cnc.machines.monthly', ['machine' => $machine, 'month' => $month, 'start' => $start] + $sheet);
    }

    public static function monthlySheet(Machine $machine, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = CncProductionRecord::where('machine_id', $machine->id)->where('status', 'completed')
            ->whereBetween('production_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('production_date', 'operation_id')
            ->selectRaw('production_date d, operation_id, SUM(quantity) q, COUNT(*) n, SUM(COALESCE(duration_minutes,0)) m, GROUP_CONCAT(DISTINCT part_sku ORDER BY part_sku SEPARATOR \', \') parts')
            ->get();
        $opIds = $rows->pluck('operation_id')->unique();
        $operations = Operation::query()->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $opIds))->ordered()->get()->all();

        $days = [];
        $opTotals = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $key = $d->toDateString();
            $dayRows = $rows->filter(fn ($r) => substr((string) $r->d, 0, 10) === $key);
            $ops = [];
            foreach ($dayRows as $r) {
                $ops[$r->operation_id] = Qty::round($r->q);
                $opTotals[$r->operation_id] = Qty::round(($opTotals[$r->operation_id] ?? 0) + $r->q);
            }
            $parts = $dayRows->pluck('parts')->flatMap(fn ($p) => explode(', ', (string) $p))->filter()->unique()->implode(', ');
            $days[] = ['date' => $key, 'day' => $d->format('D'), 'ops' => $ops, 'total' => Qty::round($dayRows->sum('q')), 'entries' => (int) $dayRows->sum('n'), 'minutes' => (int) $dayRows->sum('m'), 'parts' => $parts];
        }

        return [
            'operations' => $operations, 'days' => $days, 'opTotals' => $opTotals,
            'total' => Qty::round($rows->sum('q')), 'entries' => (int) $rows->sum('n'), 'minutes' => (int) $rows->sum('m'),
        ];
    }
}
