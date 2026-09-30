<?php

namespace App\Http\Controllers\Imported;

use App\Http\Controllers\Controller;
use App\Models\ImportedInventoryTransaction;
use App\Models\MachineAssembly;
use App\Models\MachineAssemblyItem;
use App\Models\MachineryModel;
use App\Rules\QuantityForUnit;
use App\Services\ActivityLogger;
use App\Services\ImportedInventoryService;
use App\Services\ReferenceGenerator;
use App\Support\Qty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Machine assembly / consumption. Actual consumption is ALWAYS derived from OUT transactions tagged with
 * the assembly — issuing from here creates exactly one standard OUT transaction, so nothing is deducted twice.
 */
class AssemblyController extends Controller
{
    public function __construct(private ImportedInventoryService $service) {}

    public function index(Request $request): View
    {
        $query = MachineAssembly::query()->with('machineryModel')->withCount('items');
        if ($s = $request->query('status')) {
            $query->where('status', $s);
        }
        if ($q = $request->query('q')) {
            $query->where(fn ($w) => $w->where('name', 'like', self::like($q))->orWhere('reference_no', 'like', self::like($q))->orWhere('customer', 'like', self::like($q)));
        }
        $assemblies = $query->latest('id')->paginate($this->perPage($request))->withQueryString();
        $planned = MachineAssemblyItem::whereIn('machine_assembly_id', $assemblies->pluck('id'))->groupBy('machine_assembly_id')->selectRaw('machine_assembly_id, SUM(planned_quantity) q')->pluck('q', 'machine_assembly_id');
        $issued = ImportedInventoryTransaction::whereIn('machine_assembly_id', $assemblies->pluck('id'))->where('type', 'out')->where('is_reversed', false)
            ->groupBy('machine_assembly_id')->selectRaw('machine_assembly_id, SUM(quantity_out) q')->pluck('q', 'machine_assembly_id');

        return view('imported.assemblies.index', compact('assemblies', 'planned', 'issued'));
    }

    public function create(): View
    {
        return view('imported.assemblies.form', ['assembly' => new MachineAssembly(['status' => 'planned']), 'models' => MachineryModel::active()->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $assembly = MachineAssembly::create($data + [
            'reference_no' => ($data['reference_no'] ?? null) ?: ReferenceGenerator::next('ASM'),
            'created_by' => $request->user()->id,
        ]);
        ActivityLogger::log('assembly.created', "Assembly {$assembly->reference_no} '{$assembly->name}' created", $assembly, [], $data, 'imported');

        return redirect()->route('imported.assemblies.show', $assembly)->with('success', 'Assembly created. Now add the imported parts required.');
    }

    public function show(MachineAssembly $assembly): View
    {
        $assembly->load(['machineryModel', 'items.sparePart.primaryImage', 'items.sparePart.unit']);
        $issued = $this->service->assemblyIssued($assembly->id);
        $rows = $assembly->items->map(function ($item) use ($issued) {
            $actual = $issued[$item->spare_part_id] ?? 0.0;
            $remaining = max(0, (float) $item->planned_quantity - $actual);

            return [
                'item' => $item, 'part' => $item->sparePart, 'planned' => (float) $item->planned_quantity, 'actual' => $actual,
                'remaining' => $remaining, 'available' => (float) $item->sparePart->current_stock,
                'shortage' => max(0, $remaining - (float) $item->sparePart->current_stock),
            ];
        });
        $plannedTotal = $rows->sum('planned');
        $progress = $plannedTotal > 0 ? min(100, round($rows->sum(fn ($r) => min($r['actual'], $r['planned'])) / $plannedTotal * 100)) : 0;
        $transactions = $assembly->transactions()->with(['creator', 'sparePart.primaryImage'])->latest('id')->get();

        return view('imported.assemblies.show', compact('assembly', 'rows', 'progress', 'transactions'));
    }

    public function edit(MachineAssembly $assembly): View
    {
        return view('imported.assemblies.form', ['assembly' => $assembly, 'models' => MachineryModel::orderBy('name')->get()]);
    }

    public function update(Request $request, MachineAssembly $assembly): RedirectResponse
    {
        $data = $this->validated($request, $assembly);
        $original = $assembly->getAttributes();
        if ($data['status'] === 'completed' && $assembly->status !== 'completed') {
            $data['completed_at'] = now();
        }
        $assembly->fill($data + ['updated_by' => $request->user()->id])->save();
        ActivityLogger::logChanges('assembly.updated', "Assembly {$assembly->reference_no} updated", $assembly, $original, 'imported');

        return redirect()->route('imported.assemblies.show', $assembly)->with('success', 'Assembly updated.');
    }

    public function storeItem(Request $request, MachineAssembly $assembly): RedirectResponse
    {
        abort_unless($assembly->isOpen(), 422, 'This assembly is closed.');
        $data = $request->validate([
            'spare_part_id' => ['required', Rule::exists('spare_parts', 'id')->where('inventory_type', 'imported'),
                Rule::unique('machine_assembly_items', 'spare_part_id')->where('machine_assembly_id', $assembly->id)],
            'planned_quantity' => ['required', 'numeric', 'gt:0', new QuantityForUnit(partId: (int) $request->input('spare_part_id'))],
            'remarks' => ['nullable', 'string', 'max:255'],
        ], ['spare_part_id.unique' => 'This part is already in the assembly — edit its planned quantity instead.'], ['spare_part_id' => 'part']);
        $item = $assembly->items()->create($data);
        ActivityLogger::log('assembly.item_added', "Part added to assembly {$assembly->reference_no}: planned ".Qty::fmt($data['planned_quantity']), $assembly, [], $data, 'imported');

        return back()->with('success', 'Part added to the assembly plan.');
    }

    public function updateItem(Request $request, MachineAssembly $assembly, MachineAssemblyItem $item): RedirectResponse
    {
        abort_unless($item->machine_assembly_id === $assembly->id, 404);
        $data = $request->validate(['planned_quantity' => ['required', 'numeric', 'min:0', new QuantityForUnit(partId: $item->spare_part_id)], 'remarks' => ['nullable', 'string', 'max:255']]);
        $original = $item->getAttributes();
        $item->update($data);
        ActivityLogger::logChanges('assembly.item_updated', "Planned quantity changed in assembly {$assembly->reference_no}", $item, $original, 'imported');

        return back()->with('success', 'Planned quantity updated.');
    }

    /** Issue one planned part to the assembly → creates a single standard OUT transaction. */
    public function issue(Request $request, MachineAssembly $assembly): RedirectResponse
    {
        $data = $request->validate([
            'spare_part_id' => ['required', Rule::exists('spare_parts', 'id')->where('inventory_type', 'imported')->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'gt:0', new QuantityForUnit(partId: (int) $request->input('spare_part_id'))],
            'transaction_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'collected_by' => ['required', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], [], ['collected_by' => 'person collecting']);
        [$txn, $created] = $this->service->issue($data + ['machine_assembly_id' => $assembly->id, 'machinery_model_id' => $assembly->machinery_model_id, 'purpose' => 'Assembly '.$assembly->reference_no],
            $request->input('idempotency_key'));

        return back()->with($created ? 'success' : 'info', $created
            ? 'Issued '.Qty::fmt($txn->quantity_out)." {$txn->part_sku} to this assembly ({$txn->reference_no}). Remaining stock ".Qty::fmt($txn->balance_after).'.'
            : "Already recorded as {$txn->reference_no}.");
    }

    private function validated(Request $request, ?MachineAssembly $assembly = null): array
    {
        return $request->validate([
            'reference_no' => [$assembly ? 'required' : 'nullable', 'string', 'max:40', Rule::unique('machine_assemblies', 'reference_no')->ignore($assembly?->id)],
            'name' => ['required', 'string', 'max:191'],
            'machinery_model_id' => ['nullable', Rule::exists('machinery_models', 'id')],
            'customer' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(array_keys(MachineAssembly::STATUSES))],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'target_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
