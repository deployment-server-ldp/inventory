<?php

namespace App\Http\Controllers\Cnc;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Models\CncProductionCompletion;
use App\Models\SparePart;
use App\Rules\QuantityForUnit;
use App\Services\CncProductionService;
use App\Support\DateRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Quality / production completion — the ONLY route by which CNC production reaches stock. */
class CompletionController extends Controller
{
    public function __construct(private CncProductionService $service)
    {
    }

    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'year');
        $query = CncProductionCompletion::query()->with(['sparePart.primaryImage', 'sparePart.unit', 'submitter', 'approver'])
            ->whereBetween('completion_date', [$range->fromDate(), $range->toDate()]);
        if (in_array($request->query('status'), array_keys(CncProductionCompletion::STATUSES), true)) {
            $query->where('status', $request->query('status'));
        }
        if ($p = $request->integer('spare_part_id')) {
            $query->where('spare_part_id', $p);
        }
        if ($q = $request->query('q')) {
            $query->where(fn ($w) => $w->where('reference_no', 'like', self::like($q))->orWhere('part_name', 'like', self::like($q))->orWhere('part_sku', 'like', self::like($q)));
        }
        $this->applySort($query, $request, ['date' => 'completion_date', 'ref' => 'reference_no', 'accepted' => 'quantity_accepted'], 'date', 'desc');
        $query->orderByDesc('id');

        return view('cnc.completions.index', [
            'completions' => $query->paginate($this->perPage($request))->withQueryString(),
            'range' => $range,
            'pendingCount' => CncProductionCompletion::where('status', 'pending')->count(),
            'parts' => SparePart::type('cnc')->orderBy('name')->get(['id', 'name', 'sku']),
        ]);
    }

    public function create(Request $request): View
    {
        $selected = null;
        if ($pid = $request->integer('part') ?: (int) old('spare_part_id')) {
            $p = SparePart::type('cnc')->with(['category', 'unit', 'primaryImage', 'supplier', 'finalOperation'])->find($pid);
            $selected = $p ? LookupController::present($p) + ['awaiting' => $this->service->awaitingCompletion($p->id)] : null;
        }
        // Parts that currently have output waiting for completion
        $waiting = SparePart::type('cnc')->with(['primaryImage', 'unit', 'finalOperation'])
            ->whereIn('id', \App\Models\CncProductionRecord::where('is_final_operation', true)->where('status', 'completed')->select('spare_part_id'))
            ->orderBy('name')->get()
            ->map(fn ($p) => ['part' => $p, 'awaiting' => $this->service->awaitingCompletion($p->id)])
            ->filter(fn ($r) => $r['awaiting'] > 0)->values();

        return view('cnc.completions.create', ['selected' => $selected, 'waiting' => $waiting, 'canApprove' => $request->user()->can('cnc.completion.approve')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $partId = (int) $request->input('spare_part_id');
        $data = $request->validate([
            'completion_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'spare_part_id' => ['required', Rule::exists('spare_parts', 'id')->where('inventory_type', 'cnc')],
            'quantity_accepted' => ['required', 'numeric', 'min:0', 'max:999999', new QuantityForUnit(partId: $partId)],
            'quantity_rejected' => ['nullable', 'numeric', 'min:0', 'max:999999', new QuantityForUnit(partId: $partId)],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], [], ['spare_part_id' => 'part']);
        $approveNow = $request->boolean('approve_now') && $request->user()->can('cnc.completion.approve');
        $completion = $this->service->submitCompletion($data, $approveNow, $request->input('idempotency_key'));

        return redirect()->route('cnc.completions.show', $completion)->with('success', $completion->status === 'approved'
            ? "Completion {$completion->reference_no} approved — accepted quantity added to CNC stock."
            : "Completion {$completion->reference_no} submitted and awaiting quality approval.");
    }

    public function show(CncProductionCompletion $completion): View
    {
        $completion->load(['sparePart.primaryImage', 'sparePart.unit', 'submitter', 'approver', 'reverser', 'receipt.reversal']);

        return view('cnc.completions.show', ['c' => $completion, 'awaiting' => $this->service->awaitingCompletion($completion->spare_part_id)]);
    }

    public function approve(Request $request, CncProductionCompletion $completion): RedirectResponse
    {
        $data = $request->validate([
            'quantity_accepted' => ['required', 'numeric', 'min:0', new QuantityForUnit(partId: $completion->spare_part_id)],
            'quantity_rejected' => ['required', 'numeric', 'min:0', new QuantityForUnit(partId: $completion->spare_part_id)],
            'decision_notes' => ['nullable', 'string', 'max:500'],
        ]);
        $this->service->approveCompletion($completion, (float) $data['quantity_accepted'], (float) $data['quantity_rejected'], $data['decision_notes'] ?? null);

        return back()->with('success', "Completion {$completion->reference_no} approved and posted to CNC stock.");
    }

    public function reject(Request $request, CncProductionCompletion $completion): RedirectResponse
    {
        $data = $request->validate(['decision_notes' => ['required', 'string', 'min:5', 'max:500']], [], ['decision_notes' => 'reason']);
        $this->service->rejectCompletion($completion, $data['decision_notes']);

        return back()->with('success', "Completion {$completion->reference_no} rejected. The quantity returns to the awaiting-completion pool.");
    }

    public function reverse(Request $request, CncProductionCompletion $completion): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->service->reverseCompletion($completion, $data['reason']);

        return back()->with('success', "Completion {$completion->reference_no} reversed and its stock receipt cancelled.");
    }
}
