<?php

namespace App\Http\Controllers;

use App\Models\CncProductionCompletion;
use App\Models\CncProductionRecord;
use App\Models\ImportedInventoryTransaction;
use App\Models\Machine;
use App\Models\MachineAssembly;
use App\Models\SparePart;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Global search across parts, machines and transaction references — limited to what the user may see. */
class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $u = $request->user();
        $results = [];
        if (mb_strlen($q) >= 2) {
            $like = self::like($q);
            $types = array_keys(array_filter([
                'cnc' => $u->hasAnyPermission(['cnc.inventory.view', 'cnc.parts.manage', 'cnc.production.view']),
                'imported' => $u->hasAnyPermission(['imported.inventory.view', 'imported.products.manage']),
            ]));
            if ($types) {
                $results['parts'] = SparePart::query()->whereIn('inventory_type', $types)->with(['category', 'unit', 'primaryImage'])->search($q)->orderBy('name')->limit(25)->get();
            }
            if ($u->can('cnc.production.view')) {
                $results['machines'] = Machine::where(fn ($w) => $w->where('code', 'like', $like)->orWhere('name', 'like', $like))->ordered()->limit(10)->get();
                $results['production'] = CncProductionRecord::with('machine')->where('reference_no', 'like', $like)->latest('id')->limit(10)->get();
            }
            if ($u->hasAnyPermission(['cnc.completion.create', 'cnc.completion.approve'])) {
                $results['completions'] = CncProductionCompletion::where('reference_no', 'like', $like)->latest('id')->limit(10)->get();
            }
            if ($u->can('imported.inventory.view')) {
                $results['transactions'] = ImportedInventoryTransaction::where(fn ($w) => $w->where('reference_no', 'like', $like)->orWhere('document_reference', 'like', $like))->latest('id')->limit(15)->get();
            }
            if ($u->can('imported.assembly.view')) {
                $results['assemblies'] = MachineAssembly::where(fn ($w) => $w->where('reference_no', 'like', $like)->orWhere('name', 'like', $like))->latest('id')->limit(10)->get();
            }
        }

        return view('search.index', ['q' => $q, 'results' => $results, 'count' => collect($results)->sum(fn ($c) => $c->count())]);
    }
}
