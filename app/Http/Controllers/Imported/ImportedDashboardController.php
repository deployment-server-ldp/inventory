<?php

namespace App\Http\Controllers\Imported;

use App\Http\Controllers\Controller;
use App\Models\ImportedInventoryTransaction;
use App\Models\PartCategory;
use App\Models\SparePart;
use App\Support\DateRange;
use App\Support\Trend;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ImportedDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'month');
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $cat = $request->integer('category_id') ?: null;

        $parts = fn () => SparePart::type('imported')->where('is_active', true)->when($cat, fn ($q) => $q->where('category_id', $cat));
        $txn = fn () => ImportedInventoryTransaction::query()->when($cat, fn ($q) => $q->whereHas('sparePart', fn ($p) => $p->where('category_id', $cat)));
        // Net movements exclude reversed originals AND their reversals so figures reflect real receipts / issues.
        $net = fn ($q) => $q->where('is_reversed', false);

        $kpi = [
            'products' => $parts()->count(),
            'units' => (float) $parts()->sum('current_stock'),
            'values' => $parts()->whereNotNull('unit_cost')->groupBy('currency')->selectRaw('currency, SUM(current_stock * unit_cost) v')->pluck('v', 'currency'),
            'priced' => $parts()->whereNotNull('unit_cost')->count(),
            'in_today' => (float) $net($txn()->where('type', 'in')->where('transaction_date', $today))->sum('quantity_in'),
            'in_today_n' => $net($txn()->where('type', 'in')->where('transaction_date', $today))->distinct()->count('spare_part_id'),
            'out_today' => (float) $net($txn()->where('type', 'out')->where('transaction_date', $today))->sum('quantity_out'),
            'out_today_n' => $net($txn()->where('type', 'out')->where('transaction_date', $today))->distinct()->count('spare_part_id'),
            'in_month' => (float) $net($txn()->where('type', 'in')->whereBetween('transaction_date', [$monthStart, $today]))->sum('quantity_in'),
            'out_month' => (float) $net($txn()->where('type', 'out')->whereBetween('transaction_date', [$monthStart, $today]))->sum('quantity_out'),
            'low' => $parts()->lowStock()->count(),
            'out' => $parts()->outOfStock()->count(),
        ];

        $inRange = $net($txn()->whereBetween('transaction_date', [$range->fromDate(), $range->toDate()]));
        $byCategory = SparePart::type('imported')->where('spare_parts.is_active', true)->when($cat, fn ($q) => $q->where('category_id', $cat))
            ->join('part_categories', 'part_categories.id', '=', 'spare_parts.category_id')
            ->groupBy('part_categories.id', 'part_categories.name')->orderByDesc(DB::raw('SUM(current_stock)'))
            ->selectRaw('part_categories.id, part_categories.name, SUM(current_stock) q, COUNT(*) n')->get();
        $mostIssued = (clone $inRange)->where('type', 'out')->groupBy('spare_part_id', 'part_sku', 'part_name')->orderByDesc(DB::raw('SUM(quantity_out)'))->limit(10)
            ->selectRaw('spare_part_id, part_sku, part_name, SUM(quantity_out) q, COUNT(*) n')->get();
        $byModel = (clone $inRange)->where('type', 'out')->leftJoin('machinery_models', 'machinery_models.id', '=', 'imported_inventory_transactions.machinery_model_id')
            ->groupBy('machinery_models.id', 'machinery_models.name')->orderByDesc(DB::raw('SUM(quantity_out)'))
            ->selectRaw("machinery_models.id, COALESCE(machinery_models.name, 'Other / unspecified') name, SUM(quantity_out) q")->get();
        $grain = Trend::grain($request->query('grain'), $range);
        $trend = Trend::series($inRange, 'transaction_date', $range, $grain, [
            'qin' => "SUM(CASE WHEN type='in' THEN quantity_in ELSE 0 END)",
            'qout' => "SUM(CASE WHEN type='out' THEN quantity_out ELSE 0 END)",
        ]);
        $alerts = $parts()->where(fn ($q) => $q->where('current_stock', '<=', 0)->orWhere(fn ($w) => $w->where('min_stock', '>', 0)->whereColumn('current_stock', '<=', 'min_stock')))
            ->with(['primaryImage', 'unit'])->orderBy('current_stock')->limit(10)->get();
        $recent = ImportedInventoryTransaction::with(['sparePart.primaryImage', 'creator'])->latest('id')->limit(10)->get();

        return view('imported.dashboard', compact('range', 'kpi', 'byCategory', 'mostIssued', 'byModel', 'trend', 'grain', 'alerts', 'recent') + [
            'categories' => PartCategory::forType('imported')->orderBy('name')->get(),
        ]);
    }
}
