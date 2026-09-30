<?php

namespace App\Support;

use App\Models\CncInventoryTransaction;
use App\Models\ImportedInventoryTransaction;
use App\Models\SparePart;
use Illuminate\Http\Request;

/**
 * Date-range stock ledger for either inventory: opening balance before the range, every
 * movement in the range with running balance, and the closing balance.
 */
class LedgerData
{
    public static function build(Request $request, string $type): array
    {
        $range = DateRange::fromRequest($request, 'month');
        $class = $type === 'cnc' ? CncInventoryTransaction::class : ImportedInventoryTransaction::class;
        $part = $request->integer('part_id') ? SparePart::type($type)->with(['primaryImage', 'unit', 'category'])->find($request->integer('part_id')) : null;

        $query = $class::query()->with(['creator', 'sparePart.primaryImage'])
            ->whereBetween('transaction_date', [$range->fromDate(), $range->toDate()]);
        if ($part) {
            $query->where('spare_part_id', $part->id);
        }
        if ($t = $request->query('txn_type')) {
            $query->where('type', $t);
        }
        if ($q = $request->query('q')) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
            $query->where(fn ($w) => $w->where('reference_no', 'like', $like)->orWhere('part_sku', 'like', $like)->orWhere('part_name', 'like', $like));
        }

        $summary = null;
        if ($part) {
            $opening = (float) $class::where('spare_part_id', $part->id)->where('transaction_date', '<', $range->fromDate())->selectRaw('COALESCE(SUM(quantity_in) - SUM(quantity_out), 0) b')->value('b');
            $in = (float) (clone $query)->sum('quantity_in');
            $out = (float) (clone $query)->sum('quantity_out');
            $summary = ['opening' => $opening, 'in' => $in, 'out' => $out, 'closing' => $opening + $in - $out];
        }

        $transactions = (clone $query)->orderBy('transaction_date')->orderBy('id')->paginate(50)->withQueryString();
        $pageStart = null;
        if ($summary) {
            // running balance (by transaction date) at the first row of this page
            $offset = ($transactions->currentPage() - 1) * $transactions->perPage();
            $before = $offset > 0
                ? (float) \Illuminate\Support\Facades\DB::query()->fromSub((clone $query)->reorder()->orderBy('transaction_date')->orderBy('id')->limit($offset)->select(['quantity_in', 'quantity_out']), 'x')
                    ->selectRaw('COALESCE(SUM(quantity_in) - SUM(quantity_out), 0) b')->value('b')
                : 0.0;
            $pageStart = $summary['opening'] + $before;
        }

        return [
            'type' => $type,
            'pageStart' => $pageStart,
            'part' => $part,
            'range' => $range,
            'summary' => $summary,
            'types' => $class::TYPES,
            'transactions' => $transactions,
        ];
    }
}
