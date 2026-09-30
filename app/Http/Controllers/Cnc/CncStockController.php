<?php

namespace App\Http\Controllers\Cnc;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Models\CncInventoryTransaction;
use App\Models\MachineryModel;
use App\Models\PartCategory;
use App\Models\SparePart;
use App\Rules\QuantityForUnit;
use App\Services\ActivityLogger;
use App\Services\Idempotency;
use App\Services\StockService;
use App\Support\LedgerData;
use App\Support\Qty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CncStockController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request): View
    {
        $query = SparePart::query()->type('cnc')->with(['category', 'unit', 'primaryImage'])->search($request->query('q'));
        if ($cat = $request->integer('category_id')) {
            $query->where('category_id', $cat);
        }
        match ($request->query('stock')) {
            'low' => $query->lowStock(), 'out' => $query->outOfStock(), 'in' => $query->where('current_stock', '>', 0), default => null,
        };
        if ($request->query('status', 'active') === 'active') {
            $query->where('is_active', true);
        }
        $totals = (clone $query)->selectRaw('COUNT(*) n, SUM(current_stock) units')->first();
        $this->applySort($query, $request, ['name' => 'name', 'sku' => 'sku', 'stock' => 'current_stock', 'min' => 'min_stock'], 'name', 'asc');

        return view('cnc.stock.index', [
            'parts' => $query->paginate($this->perPage($request))->withQueryString(),
            'totals' => $totals,
            'categories' => PartCategory::forType('cnc')->orderBy('name')->get(),
        ]);
    }

    public function ledger(Request $request): View
    {
        return view('stock.ledger', LedgerData::build($request, 'cnc'));
    }

    public function issueForm(Request $request): View
    {
        $selected = null;
        if ($pid = $request->integer('part') ?: (int) old('spare_part_id')) {
            $p = SparePart::type('cnc')->with(['category', 'unit', 'primaryImage', 'supplier', 'finalOperation'])->find($pid);
            $selected = $p ? LookupController::present($p) : null;
        }

        return view('cnc.stock.issue', ['selected' => $selected, 'models' => MachineryModel::active()->orderBy('name')->get()]);
    }

    public function issue(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'transaction_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'spare_part_id' => ['required', Rule::exists('spare_parts', 'id')->where('inventory_type', 'cnc')->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999', new QuantityForUnit(partId: (int) $request->input('spare_part_id'))],
            'issued_to' => ['required', 'string', 'max:150'],
            'machinery_model_id' => ['nullable', Rule::exists('machinery_models', 'id')],
            'purpose' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], [], ['spare_part_id' => 'part']);
        $key = $request->input('idempotency_key');
        [$txn, $created] = Idempotency::run(CncInventoryTransaction::class, $key, function () use ($data, $key) {
            $txn = $this->stock->post('cnc', (int) $data['spare_part_id'], 'issue', (float) $data['quantity'], [
                'transaction_date' => $data['transaction_date'], 'issued_to' => $data['issued_to'], 'machinery_model_id' => $data['machinery_model_id'] ?? null,
                'purpose' => $data['purpose'] ?? null, 'remarks' => $data['remarks'] ?? null, 'idempotency_key' => $key,
            ]);
            ActivityLogger::log('cnc.issue', "CNC stock issue {$txn->reference_no}: −".Qty::fmt($txn->quantity_out)." {$txn->part_sku} to {$txn->issued_to} (balance ".Qty::fmt($txn->balance_after).')', $txn, [], $txn->only(['spare_part_id', 'quantity_out', 'balance_after', 'issued_to']), 'cnc');

            return $txn;
        });

        return redirect()->route('cnc.stock.ledger', ['part_id' => $txn->spare_part_id, 'period' => 'month'])
            ->with($created ? 'success' : 'info', $created ? "Issued {$txn->reference_no}. Remaining stock: ".Qty::fmt($txn->balance_after) : "This issue was already recorded as {$txn->reference_no}.");
    }

    public function reverse(Request $request, CncInventoryTransaction $transaction): RedirectResponse
    {
        abort_unless($transaction->type === 'issue', 422, 'Only stock issues can be reversed here. Production receipts are reversed from the completion.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $rev = $this->stock->reverse('cnc', $transaction->id, $data['reason']);

        return back()->with('success', "Issue {$transaction->reference_no} reversed by {$rev->reference_no}.");
    }
}
