<?php

namespace App\Http\Controllers;

use App\Models\CncInventoryTransaction;
use App\Models\CncProductionRecord;
use App\Models\ImportedInventoryTransaction;
use App\Models\Machine;
use App\Models\PartCategory;
use App\Models\SparePart;
use App\Support\DateRange;
use App\Support\Trend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Executive dashboard. Both inventory streams are shown side by side but their balances are never merged;
 * the only combined figure is an explicitly labelled unit count. Sections are shown only for the streams
 * the user is permitted to see.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user->can('dashboard.overall')) {
            foreach (['cnc.dashboard' => 'cnc.dashboard', 'imported.dashboard' => 'imported.dashboard', 'cnc.production.view' => 'cnc.production.index', 'imported.inventory.view' => 'imported.stock.index'] as $perm => $route) {
                if ($user->can($perm)) {
                    return redirect()->route($route);
                }
            }
            abort(403, 'No dashboard is assigned to your role. Contact the administrator.');
        }

        $range = DateRange::fromRequest($request, 'month');
        $type = in_array($request->query('inventory_type'), ['cnc', 'imported'], true) ? $request->query('inventory_type') : 'all';
        $showCnc = $type !== 'imported' && $user->hasAnyPermission(['cnc.dashboard', 'cnc.inventory.view', 'cnc.production.view']);
        $showImp = $type !== 'cnc' && $user->hasAnyPermission(['imported.dashboard', 'imported.inventory.view']);
        $cat = $request->integer('category_id') ?: null;
        $machineId = $request->integer('machine_id') ?: null;
        $partId = $request->integer('spare_part_id') ?: null;
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $parts = fn (string $t) => SparePart::type($t)->where('is_active', true)->when($cat, fn ($q) => $q->where('category_id', $cat))->when($partId, fn ($q) => $q->whereKey($partId));
        $data = ['range' => $range, 'type' => $type, 'showCnc' => $showCnc, 'showImp' => $showImp, 'kpi' => []];

        if ($showCnc) {
            $prod = fn () => CncProductionRecord::query()->where('status', 'completed')->where('is_final_operation', true)
                ->when($machineId, fn ($q) => $q->where('machine_id', $machineId))->when($partId, fn ($q) => $q->where('spare_part_id', $partId))
                ->when($cat, fn ($q) => $q->whereHas('sparePart', fn ($p) => $p->where('category_id', $cat)));
            $runningIds = CncProductionRecord::where('status', 'running')->distinct()->pluck('machine_id');
            $data['kpi'] += [
                'cnc_skus' => $parts('cnc')->count(),
                'cnc_units' => (float) $parts('cnc')->sum('current_stock'),
                'cnc_today' => (float) $prod()->where('production_date', $today)->sum('quantity'),
                'cnc_month' => (float) $prod()->whereBetween('production_date', [$monthStart, $today])->sum('quantity'),
                'cnc_range' => (float) $prod()->whereBetween('production_date', [$range->fromDate(), $range->toDate()])->sum('quantity'),
                'machines_active' => Machine::where('status', 'active')->count(),
                'machines_running' => $runningIds->count(),
                'cnc_low' => $parts('cnc')->lowStock()->count(),
                'cnc_out' => $parts('cnc')->outOfStock()->count(),
            ];
            $data['cncAlerts'] = $parts('cnc')->where(fn ($q) => $q->where('current_stock', '<=', 0)->orWhere(fn ($w) => $w->where('min_stock', '>', 0)->whereColumn('current_stock', '<=', 'min_stock')))
                ->with(['primaryImage', 'unit'])->orderBy('current_stock')->limit(6)->get();
            $inRange = $prod()->whereBetween('production_date', [$range->fromDate(), $range->toDate()]);
            $data['topManufactured'] = (clone $inRange)->groupBy('spare_part_id', 'part_sku', 'part_name')->orderByDesc(DB::raw('SUM(quantity)'))->limit(8)
                ->selectRaw('spare_part_id, part_sku, part_name, SUM(quantity) q')->get();
            $data['byMachine'] = CncProductionRecord::query()->where('cnc_production_records.status', 'completed')->whereBetween('production_date', [$range->fromDate(), $range->toDate()])
                ->when($machineId, fn ($q) => $q->where('machine_id', $machineId))->when($partId, fn ($q) => $q->where('spare_part_id', $partId))
                ->join('machines', 'machines.id', '=', 'cnc_production_records.machine_id')->groupBy('machines.id', 'machines.code', 'machines.sort_order')
                ->orderBy('machines.sort_order')->orderBy('machines.id')
                ->selectRaw('machines.id, machines.code, SUM(CASE WHEN is_final_operation=1 THEN quantity ELSE 0 END) f, SUM(quantity) q, COUNT(*) n')->get();
            $grain = Trend::grain($request->query('grain'), $range);
            $data['cncTrend'] = Trend::series($inRange, 'production_date', $range, $grain, ['q' => 'SUM(quantity)']);
            $data['recentProduction'] = CncProductionRecord::with(['machine', 'operator', 'sparePart.primaryImage'])
                ->when($machineId, fn ($q) => $q->where('machine_id', $machineId))->when($partId, fn ($q) => $q->where('spare_part_id', $partId))
                ->latest('id')->limit(8)->get();
        }

        if ($showImp) {
            $txn = fn () => ImportedInventoryTransaction::query()->where('is_reversed', false)->when($partId, fn ($q) => $q->where('spare_part_id', $partId))
                ->when($cat, fn ($q) => $q->whereHas('sparePart', fn ($p) => $p->where('category_id', $cat)));
            $data['kpi'] += [
                'imp_skus' => $parts('imported')->count(),
                'imp_units' => (float) $parts('imported')->sum('current_stock'),
                'in_today' => (float) $txn()->where('type', 'in')->where('transaction_date', $today)->sum('quantity_in'),
                'in_month' => (float) $txn()->where('type', 'in')->whereBetween('transaction_date', [$monthStart, $today])->sum('quantity_in'),
                'out_today' => (float) $txn()->where('type', 'out')->where('transaction_date', $today)->sum('quantity_out'),
                'out_month' => (float) $txn()->where('type', 'out')->whereBetween('transaction_date', [$monthStart, $today])->sum('quantity_out'),
                'imp_low' => $parts('imported')->lowStock()->count(),
                'imp_out' => $parts('imported')->outOfStock()->count(),
            ];
            $data['impAlerts'] = $parts('imported')->where(fn ($q) => $q->where('current_stock', '<=', 0)->orWhere(fn ($w) => $w->where('min_stock', '>', 0)->whereColumn('current_stock', '<=', 'min_stock')))
                ->with(['primaryImage', 'unit'])->orderBy('current_stock')->limit(6)->get();
            $outRange = $txn()->where('type', 'out')->whereBetween('transaction_date', [$range->fromDate(), $range->toDate()]);
            $data['mostIssued'] = (clone $outRange)->groupBy('spare_part_id', 'part_sku', 'part_name')->orderByDesc(DB::raw('SUM(quantity_out)'))->limit(8)
                ->selectRaw('spare_part_id, part_sku, part_name, SUM(quantity_out) q, COUNT(*) n')->get();
            $data['consumptionByModel'] = (clone $outRange)->leftJoin('machinery_models', 'machinery_models.id', '=', 'imported_inventory_transactions.machinery_model_id')
                ->groupBy('machinery_models.id', 'machinery_models.name')->orderByDesc(DB::raw('SUM(quantity_out)'))->limit(10)
                ->selectRaw("machinery_models.id, COALESCE(machinery_models.name, 'Other / unspecified') name, SUM(quantity_out) q")->get();
        }

        // Category-wise inventory — separate series per stream
        $typeKeys = array_keys(array_filter(['cnc' => $showCnc, 'imported' => $showImp]));
        $catRows = $typeKeys ? SparePart::query()->whereIn('inventory_type', $typeKeys)->where('spare_parts.is_active', true)
            ->when($cat, fn ($q) => $q->where('category_id', $cat))
            ->join('part_categories', 'part_categories.id', '=', 'spare_parts.category_id')
            ->groupBy('part_categories.id', 'part_categories.name', 'inventory_type')
            ->selectRaw('part_categories.id, part_categories.name, inventory_type, SUM(current_stock) q')->get() : collect();
        $data['categoryChart'] = [
            'labels' => $catRows->pluck('name')->unique()->values(),
            'cnc' => $catRows->pluck('name')->unique()->values()->map(fn ($n) => (float) ($catRows->first(fn ($r) => $r->name === $n && $r->inventory_type === 'cnc')->q ?? 0)),
            'imported' => $catRows->pluck('name')->unique()->values()->map(fn ($n) => (float) ($catRows->first(fn ($r) => $r->name === $n && $r->inventory_type === 'imported')->q ?? 0)),
        ];

        // Recent stock movements across both ledgers (each clearly labelled)
        $moves = collect();
        if ($showCnc) {
            $moves = $moves->merge(CncInventoryTransaction::with(['sparePart.primaryImage', 'creator'])->when($partId, fn ($q) => $q->where('spare_part_id', $partId))->latest('id')->limit(8)->get()->each(fn ($t) => $t->stream = 'cnc'));
        }
        if ($showImp) {
            $moves = $moves->merge(ImportedInventoryTransaction::with(['sparePart.primaryImage', 'creator'])->when($partId, fn ($q) => $q->where('spare_part_id', $partId))->latest('id')->limit(8)->get()->each(fn ($t) => $t->stream = 'imported'));
        }
        $data['recentMoves'] = $moves->sortByDesc('created_at')->take(10)->values();

        $data['categories'] = PartCategory::orderBy('name')->get();
        $data['machines'] = $showCnc ? Machine::ordered()->get() : collect();
        $data['parts'] = SparePart::query()->whereIn('inventory_type', $typeKeys ?: ['none'])->orderBy('name')->get(['id', 'sku', 'name', 'inventory_type']);

        return view('dashboard.index', $data);
    }
}
