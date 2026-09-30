<?php

namespace App\Http\Controllers;

use App\Models\SparePart;
use App\Services\CncProductionService;
use App\Support\Qty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** JSON endpoints used by the searchable part pickers. */
class LookupController extends Controller
{
    public function parts(Request $request, string $type): JsonResponse
    {
        $prefix = $type === 'cnc' ? 'cnc.' : 'imported.';
        abort_unless(collect($request->user()->permissionNames())->contains(fn ($p) => str_starts_with($p, $prefix)) || $request->user()->isSuperAdmin(), 403);

        $query = SparePart::query()->type($type)->with(['category', 'unit', 'primaryImage', 'supplier', 'finalOperation'])
            ->search($request->query('q'));
        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        if ($request->boolean('in_stock')) {
            $query->where('current_stock', '>', 0);
        }
        $parts = $query->orderBy('name')->limit(50)->get();

        return response()->json(['data' => $parts->map(fn ($p) => self::present($p))->values()]);
    }

    public function completionPool(SparePart $part, CncProductionService $cnc): JsonResponse
    {
        abort_unless($part->isCnc(), 404);

        return response()->json([
            'awaiting' => Qty::fmt($cnc->awaitingCompletion($part->id)),
            'awaiting_raw' => $cnc->awaitingCompletion($part->id),
            'progress' => $cnc->operationProgress($part->id),
        ]);
    }

    public static function present(SparePart $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'part_number' => $p->part_number,
            'brand' => $p->brand,
            'category' => $p->category?->name,
            'category_id' => $p->category_id,
            'unit' => $p->unit?->symbol,
            'unit_name' => $p->unit?->name,
            'allows_decimal' => (bool) $p->unit?->allows_decimal,
            'specification' => $p->specification,
            'description' => $p->description,
            'supplier' => $p->supplier?->name,
            'supplier_id' => $p->supplier_id,
            'unit_cost' => $p->unit_cost,
            'currency' => $p->currency,
            'final_operation' => $p->finalOperation?->name,
            'stock' => Qty::fmt($p->current_stock),
            'stock_raw' => (float) $p->current_stock,
            'min_stock' => Qty::fmt($p->min_stock),
            'thumb' => $p->thumbUrl(),
            'image' => $p->imageUrl(),
            'is_active' => $p->is_active,
        ];
    }
}
