<?php

namespace App\Http\Controllers\Imported;

use App\Http\Controllers\Controller;
use App\Models\PartCategory;
use App\Models\SparePart;
use App\Support\LedgerData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImportedStockController extends Controller
{
    public function index(Request $request): View
    {
        $query = SparePart::query()->type('imported')->with(['category', 'unit', 'primaryImage', 'supplier'])->search($request->query('q'));
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
        $values = (clone $query)->whereNotNull('unit_cost')->groupBy('currency')->selectRaw('currency, SUM(current_stock * unit_cost) v')->pluck('v', 'currency');
        $this->applySort($query, $request, ['name' => 'name', 'sku' => 'sku', 'stock' => 'current_stock', 'min' => 'min_stock'], 'name', 'asc');

        return view('imported.stock.index', [
            'parts' => $query->paginate($this->perPage($request))->withQueryString(),
            'totals' => $totals, 'values' => $values,
            'categories' => PartCategory::forType('imported')->orderBy('name')->get(),
        ]);
    }

    public function ledger(Request $request): View
    {
        return view('stock.ledger', LedgerData::build($request, 'imported'));
    }
}
