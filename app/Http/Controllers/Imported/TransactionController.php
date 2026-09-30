<?php

namespace App\Http\Controllers\Imported;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Models\ImportedInventoryTransaction;
use App\Models\MachineAssembly;
use App\Models\MachineryModel;
use App\Models\PartCategory;
use App\Models\SparePart;
use App\Models\Supplier;
use App\Rules\QuantityForUnit;
use App\Services\ImportedInventoryService;
use App\Services\StockService;
use App\Support\DateRange;
use App\Support\Qty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __construct(private ImportedInventoryService $service, private StockService $stock)
    {
    }

    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'month');
        $query = self::filteredQuery($request, $range)->with(['sparePart.primaryImage', 'creator', 'machineryModel', 'assembly', 'supplier']);
        $totals = (clone $query)->reorder()->selectRaw('COUNT(*) n, SUM(quantity_in) qin, SUM(quantity_out) qout')->first();
        $this->applySort($query, $request, ['date' => 'transaction_date', 'ref' => 'reference_no', 'part' => 'part_name', 'in' => 'quantity_in', 'out' => 'quantity_out'], 'date', 'desc');
        $query->orderByDesc('id');

        return view('imported.transactions.index', [
            'transactions' => $query->paginate($this->perPage($request))->withQueryString(),
            'totals' => $totals, 'range' => $range,
            'categories' => PartCategory::forType('imported')->orderBy('name')->get(),
            'models' => MachineryModel::orderBy('name')->get(),
            'assemblies' => MachineAssembly::orderByDesc('id')->get(['id', 'reference_no', 'name']),
        ]);
    }

    public static function filteredQuery(Request $request, DateRange $range)
    {
        $query = ImportedInventoryTransaction::query()->whereBetween('transaction_date', [$range->fromDate(), $range->toDate()]);
        $type = $request->query('type');
        if (in_array($type, array_keys(ImportedInventoryTransaction::TYPES), true)) {
            $query->where('type', $type);
        }
        foreach (['spare_part_id', 'machinery_model_id', 'machine_assembly_id', 'supplier_id'] as $f) {
            if ($v = $request->integer($f)) {
                $query->where($f, $v);
            }
        }
        if ($c = $request->integer('category_id')) {
            $query->whereHas('sparePart', fn ($p) => $p->where('category_id', $c));
        }
        if ($q = $request->query('q')) {
            $like = self::like($q);
            $query->where(fn ($w) => $w->where('reference_no', 'like', $like)->orWhere('part_name', 'like', $like)->orWhere('part_sku', 'like', $like)
                ->orWhere('document_reference', 'like', $like)->orWhere('collected_by', 'like', $like));
        }

        return $query;
    }

    public function show(ImportedInventoryTransaction $transaction): View
    {
        $transaction->load(['sparePart.primaryImage', 'sparePart.unit', 'creator', 'machineryModel', 'assembly', 'supplier', 'reversalOf', 'reversal', 'adjustment']);

        return view('imported.transactions.show', ['t' => $transaction]);
    }

    public function createIn(Request $request): View
    {
        return view('imported.transactions.in', ['selected' => $this->selected($request), 'suppliers' => Supplier::active()->orderBy('name')->get()]);
    }

    public function storeIn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'transaction_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'spare_part_id' => ['required', Rule::exists('spare_parts', 'id')->where('inventory_type', 'imported')->where('is_active', true)],
            'specification' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999', new QuantityForUnit(partId: (int) $request->input('spare_part_id'))],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')],
            'source' => ['nullable', 'string', 'max:150'],
            'document_reference' => ['nullable', 'string', 'max:100'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'currency' => ['nullable', 'required_with:unit_cost', Rule::in(config('spims.currencies'))],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], ['spare_part_id.exists' => 'Select an active imported product.'], ['spare_part_id' => 'product']);
        [$txn, $created] = $this->service->receive($data, $request->input('idempotency_key'));

        return $this->after($request, $txn, $created, 'in');
    }

    public function createOut(Request $request): View
    {
        return view('imported.transactions.out', [
            'selected' => $this->selected($request),
            'models' => MachineryModel::active()->orderBy('name')->get(),
            'assemblies' => MachineAssembly::whereIn('status', ['planned', 'in_progress'])->orderByDesc('id')->get(),
            'assemblyId' => $request->integer('assembly') ?: old('machine_assembly_id'),
            'canManual' => $request->user()->can('imported.purpose.manual'),
        ]);
    }

    public function storeOut(Request $request): RedirectResponse
    {
        $partId = (int) $request->input('spare_part_id');
        $rules = [
            'transaction_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'spare_part_id' => ['required', Rule::exists('spare_parts', 'id')->where('inventory_type', 'imported')->where('is_active', true)],
            'specification' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999', new QuantityForUnit(partId: $partId)],
            'machinery_model_id' => ['nullable', Rule::exists('machinery_models', 'id')],
            'machine_assembly_id' => ['nullable', Rule::exists('machine_assemblies', 'id')],
            'collected_by' => ['required', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'purpose' => $request->user()->can('imported.purpose.manual') ? ['nullable', 'string', 'max:255'] : ['prohibited'],
        ];
        $data = $request->validate($rules, [
            'purpose.prohibited' => 'You are not authorised to enter a free-text purpose; select a machinery model instead.',
            'spare_part_id.exists' => 'Select an active imported product.',
        ], ['spare_part_id' => 'product', 'collected_by' => 'person collecting']);
        if (empty($data['machinery_model_id']) && empty($data['purpose']) && empty($data['machine_assembly_id'])) {
            return back()->withInput()->withErrors(['machinery_model_id' => 'Select the machine / machinery model (or assembly) the parts are issued for.']);
        }
        // Friendly pre-check; the authoritative check happens under a row lock in StockService.
        $available = (float) SparePart::whereKey($partId)->value('current_stock');
        if ((float) $data['quantity'] > $available && ! ImportedInventoryTransaction::where('idempotency_key', $request->input('idempotency_key'))->exists()) {
            return back()->withInput()->withErrors(['quantity' => 'Only '.Qty::fmt($available).' available. You cannot issue '.Qty::fmt($data['quantity']).'.']);
        }
        [$txn, $created] = $this->service->issue($data, $request->input('idempotency_key'));

        return $this->after($request, $txn, $created, 'out');
    }

    public function reverse(Request $request, ImportedInventoryTransaction $transaction): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $rev = $this->stock->reverse('imported', $transaction->id, $data['reason']);

        return redirect()->route('imported.transactions.show', $transaction)->with('success', "Transaction reversed by {$rev->reference_no}. Balance now ".Qty::fmt($rev->balance_after).'.');
    }

    private function after(Request $request, ImportedInventoryTransaction $txn, bool $created, string $dir): RedirectResponse
    {
        $msg = $created
            ? ($dir === 'in' ? 'Received' : 'Issued').' '.Qty::fmt($dir === 'in' ? $txn->quantity_in : $txn->quantity_out)." {$txn->part_sku} — {$txn->reference_no}. Available stock is now ".Qty::fmt($txn->balance_after).'.'
            : "This submission was already saved as {$txn->reference_no}; no duplicate stock movement was created.";
        $level = $created ? 'success' : 'info';
        if ($request->input('after') === 'new') {
            return redirect()->route($dir === 'in' ? 'imported.in.create' : 'imported.out.create', $dir === 'out' && $txn->machine_assembly_id ? ['assembly' => $txn->machine_assembly_id] : [])->with($level, $msg);
        }

        return redirect()->route('imported.transactions.show', $txn)->with($level, $msg);
    }

    private function selected(Request $request): ?array
    {
        $pid = $request->integer('part') ?: (int) old('spare_part_id');
        if (! $pid) {
            return null;
        }
        $p = SparePart::type('imported')->with(['category', 'unit', 'primaryImage', 'supplier', 'finalOperation'])->find($pid);

        return $p ? LookupController::present($p) : null;
    }
}
