<?php

namespace App\Http\Controllers\Cnc;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Models\CncProductionCompletion;
use App\Models\CncProductionRecord;
use App\Models\Machine;
use App\Models\MachineryModel;
use App\Models\Operation;
use App\Models\Operator;
use App\Models\PartCategory;
use App\Models\SparePart;
use App\Rules\QuantityForUnit;
use App\Services\CncProductionService;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function __construct(private CncProductionService $service) {}

    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'month');
        $query = self::filteredQuery($request, $range)->with(['machine', 'operator', 'sparePart.primaryImage', 'machineryModel']);
        $totals = (clone $query)->reorder()->selectRaw("COUNT(*) AS entries, SUM(CASE WHEN status='completed' THEN quantity ELSE 0 END) AS qty, SUM(COALESCE(duration_minutes,0)) AS minutes")->first();
        $this->applySort($query, $request, [
            'date' => 'production_date', 'ref' => 'reference_no', 'qty' => 'quantity', 'machine' => 'machine_id', 'part' => 'part_name', 'duration' => 'duration_minutes',
        ], 'date', 'desc');
        $query->orderByDesc('id');

        return view('cnc.production.index', [
            'records' => $query->paginate($this->perPage($request))->withQueryString(),
            'totals' => $totals,
            'range' => $range,
        ] + $this->filterOptions());
    }

    /** Shared filter logic (also used by machine history & reports). */
    public static function filteredQuery(Request $request, DateRange $range): Builder
    {
        $query = CncProductionRecord::query()->whereBetween('production_date', [$range->fromDate(), $range->toDate()]);
        foreach (['machine_id', 'spare_part_id', 'operator_id', 'operation_id', 'machinery_model_id'] as $f) {
            if ($v = $request->integer($f)) {
                $query->where($f, $v);
            }
        }
        $status = $request->query('status');
        if (in_array($status, array_keys(CncProductionRecord::STATUSES), true)) {
            $query->where('status', $status);
        } elseif ($status !== 'all') {
            $query->where('status', '!=', 'cancelled');
        }
        if ($q = $request->query('q')) {
            $query->where(fn ($w) => $w->where('reference_no', 'like', self::like($q))->orWhere('part_name', 'like', self::like($q))->orWhere('part_sku', 'like', self::like($q)));
        }
        if ($cat = $request->integer('category_id')) {
            $query->whereHas('sparePart', fn ($p) => $p->where('category_id', $cat));
        }

        return $query;
    }

    public function create(Request $request): View
    {
        $selected = null;
        if ($pid = $request->integer('part') ?: (int) old('spare_part_id')) {
            $p = SparePart::type('cnc')->with(['category', 'unit', 'primaryImage', 'supplier', 'finalOperation'])->find($pid);
            $selected = $p ? LookupController::present($p) : null;
        }

        return view('cnc.production.form', [
            'record' => new CncProductionRecord(['production_date' => now(), 'machine_id' => $request->integer('machine_id') ?: null]),
            'selected' => $selected,
        ] + $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $record = $this->service->create($data, $request->input('idempotency_key'));

        if ($request->input('after') === 'new') {
            return redirect()->route('cnc.production.create', ['machine_id' => $record->machine_id])
                ->with('success', "Production entry {$record->reference_no} saved ({$record->status}).");
        }

        return redirect()->route('cnc.production.show', $record)->with('success', "Production entry {$record->reference_no} saved.");
    }

    public function show(CncProductionRecord $record): View
    {
        $record->load(['machine', 'operator', 'sparePart.primaryImage', 'sparePart.unit', 'machineryModel', 'operation', 'creator', 'canceller']);

        return view('cnc.production.show', [
            'record' => $record,
            'awaiting' => $this->service->awaitingCompletion($record->spare_part_id),
        ]);
    }

    public function edit(CncProductionRecord $record): View|RedirectResponse
    {
        if ($record->status === 'cancelled') {
            return redirect()->route('cnc.production.show', $record)->withErrors(['stock' => 'Cancelled records cannot be edited.']);
        }
        $record->load('sparePart.category', 'sparePart.unit', 'sparePart.primaryImage', 'sparePart.supplier', 'sparePart.finalOperation');

        return view('cnc.production.form', ['record' => $record, 'selected' => LookupController::present($record->sparePart)] + $this->formOptions($record));
    }

    public function update(Request $request, CncProductionRecord $record): RedirectResponse
    {
        $data = $this->validated($request);
        $this->service->update($record, $data);

        return redirect()->route('cnc.production.show', $record)->with('success', 'Production entry updated.');
    }

    public function finish(Request $request, CncProductionRecord $record): RedirectResponse
    {
        $data = $request->validate([
            'end_time' => ['required', 'date_format:H:i'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999', new QuantityForUnit(partId: $record->spare_part_id)],
            'finish_remarks' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->service->finish($record, $data['end_time'], (float) $data['quantity'], $data['finish_remarks'] ?? null);

        return back()->with('success', "Production {$record->reference_no} finished.");
    }

    public function cancel(Request $request, CncProductionRecord $record): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->service->cancel($record, $data['reason']);

        return redirect()->route('cnc.production.show', $record)->with('success', "Production {$record->reference_no} cancelled. It no longer counts in production totals.");
    }

    /** Work-in-progress per part & operation (cumulative), with the completion pool. */
    public function progress(Request $request): View
    {
        $operations = Operation::ordered()->get();
        $parts = SparePart::query()->type('cnc')->with(['primaryImage', 'unit', 'finalOperation', 'category'])->search($request->query('q'));
        if ($cat = $request->integer('category_id')) {
            $parts->where('category_id', $cat);
        }
        $parts->whereIn('id', CncProductionRecord::query()->select('spare_part_id'));
        $parts = $parts->orderBy('name')->paginate($this->perPage($request))->withQueryString();
        $ids = $parts->pluck('id');

        $byOp = CncProductionRecord::query()->whereIn('spare_part_id', $ids)->where('status', 'completed')
            ->groupBy('spare_part_id', 'operation_id')->selectRaw('spare_part_id, operation_id, SUM(quantity) AS qty')->get()
            ->groupBy('spare_part_id')->map(fn ($rows) => $rows->pluck('qty', 'operation_id'));
        $finalOut = CncProductionRecord::query()->whereIn('spare_part_id', $ids)->where('status', 'completed')->where('is_final_operation', true)
            ->groupBy('spare_part_id')->selectRaw('spare_part_id, SUM(quantity) AS qty')->pluck('qty', 'spare_part_id');
        $running = CncProductionRecord::query()->whereIn('spare_part_id', $ids)->where('status', 'running')
            ->groupBy('spare_part_id')->selectRaw('spare_part_id, COUNT(*) AS n')->pluck('n', 'spare_part_id');
        $comp = CncProductionCompletion::query()->whereIn('spare_part_id', $ids)->whereIn('status', ['pending', 'approved'])
            ->groupBy('spare_part_id')
            ->selectRaw("spare_part_id, SUM(quantity_inspected) AS inspected, SUM(CASE WHEN status='pending' THEN quantity_inspected ELSE 0 END) AS pending, SUM(CASE WHEN status='approved' THEN quantity_accepted ELSE 0 END) AS accepted, SUM(CASE WHEN status='approved' THEN quantity_rejected ELSE 0 END) AS rejected")
            ->get()->keyBy('spare_part_id');

        return view('cnc.production.progress', [
            'parts' => $parts, 'operations' => $operations, 'byOp' => $byOp, 'finalOut' => $finalOut, 'running' => $running, 'comp' => $comp,
            'categories' => PartCategory::forType('cnc')->orderBy('name')->get(),
        ]);
    }

    // ------------------------------------------------------------------

    private function validated(Request $request): array
    {
        return $request->validate([
            'production_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'machine_id' => ['required', Rule::exists('machines', 'id')],
            'spare_part_id' => ['required', Rule::exists('spare_parts', 'id')->where('inventory_type', 'cnc')],
            'machinery_model_id' => ['nullable', Rule::exists('machinery_models', 'id')],
            'operation_id' => ['required', Rule::exists('operations', 'id')],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'different:start_time'],
            'quantity' => ['nullable', 'required_with:end_time', 'numeric', 'min:0', 'max:999999', new QuantityForUnit(partId: (int) $request->input('spare_part_id'))],
            'operator_id' => ['required', Rule::exists('operators', 'id')],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], [
            'quantity.required_with' => 'Enter the quantity produced when an end time is given (leave end time empty if the job is still running).',
            'end_time.different' => 'End time must differ from start time.',
            'production_date.before_or_equal' => 'Production date cannot be in the future.',
        ], [
            'spare_part_id' => 'part', 'machine_id' => 'CNC machine', 'operator_id' => 'operator', 'operation_id' => 'operation', 'machinery_model_id' => 'target machinery model',
        ]);
    }

    private function formOptions(?CncProductionRecord $record = null): array
    {
        $keep = fn (string $col) => fn ($q) => $record ? $q->orWhere('id', $record->{$col}) : $q;

        return [
            'machines' => Machine::query()->where(fn ($q) => $keep('machine_id')($q->where('status', 'active')))->ordered()->get(),
            'operators' => Operator::query()->where(fn ($q) => $keep('operator_id')($q->where('is_active', true)))->orderBy('name')->get(),
            'operations' => Operation::query()->where(fn ($q) => $keep('operation_id')($q->where('is_active', true)))->ordered()->get(),
            'models' => MachineryModel::query()->where(fn ($q) => $keep('machinery_model_id')($q->where('is_active', true)))->orderBy('name')->get(),
        ];
    }

    private function filterOptions(): array
    {
        return [
            'machines' => Machine::ordered()->get(),
            'operators' => Operator::orderBy('name')->get(),
            'operations' => Operation::ordered()->get(),
            'parts' => SparePart::type('cnc')->orderBy('name')->get(['id', 'name', 'sku']),
        ];
    }
}
