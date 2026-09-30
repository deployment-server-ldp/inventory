<?php

namespace App\Http\Controllers;

use App\Models\SparePart;
use App\Models\StockAdjustment;
use App\Rules\QuantityForUnit;
use App\Services\StockService;
use App\Support\DateRange;
use App\Support\Qty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $types = array_values(array_filter(['cnc', 'imported'], fn ($t) => $user->can("{$t}.stock.adjust")));
        $range = DateRange::fromRequest($request, 'year');
        $query = StockAdjustment::query()->with(['sparePart.primaryImage', 'sparePart.unit', 'creator'])->whereIn('inventory_type', $types)
            ->whereBetween('adjustment_date', [$range->fromDate(), $range->toDate()]);
        if (in_array($request->query('type'), $types, true)) {
            $query->where('inventory_type', $request->query('type'));
        }
        if ($q = $request->query('q')) {
            $query->where(fn ($w) => $w->where('reference_no', 'like', self::like($q))->orWhere('reason', 'like', self::like($q))
                ->orWhereHas('sparePart', fn ($p) => $p->where('sku', 'like', self::like($q))->orWhere('name', 'like', self::like($q))));
        }

        return view('adjustments.index', ['adjustments' => $query->latest('id')->paginate($this->perPage($request))->withQueryString(), 'types' => $types, 'range' => $range]);
    }

    public function create(Request $request, string $type): View
    {
        abort_unless($request->user()->can("{$type}.stock.adjust"), 403);
        $selected = null;
        if ($pid = $request->integer('part') ?: (int) old('spare_part_id')) {
            $p = SparePart::type($type)->with(['category', 'unit', 'primaryImage', 'supplier', 'finalOperation'])->find($pid);
            $selected = $p ? LookupController::present($p) : null;
        }

        return view('adjustments.create', ['type' => $type, 'selected' => $selected]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        abort_unless($request->user()->can("{$type}.stock.adjust"), 403);
        $data = $request->validate([
            'spare_part_id' => ['required', Rule::exists('spare_parts', 'id')->where('inventory_type', $type)],
            'adjustment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999', new QuantityForUnit(partId: (int) $request->input('spare_part_id'))],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], ['reason.required' => 'A reason is mandatory for every stock adjustment.'], ['spare_part_id' => 'part']);
        $adj = $this->stock->adjust($type, (int) $data['spare_part_id'], $data['direction'], (float) $data['quantity'], $data['reason'], $data['adjustment_date'], $data['remarks'] ?? null);

        return redirect()->route('adjustments.index', ['type' => $type])->with('success', "Adjustment {$adj->reference_no} posted: ".($data['direction'] === 'in' ? '+' : '−').Qty::fmt($data['quantity']).'.');
    }
}
