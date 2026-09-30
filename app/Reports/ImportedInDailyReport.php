<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportedInDailyReport extends Report
{
    public string $key = 'imported-in-daily';

    public string $title = 'Daily imported IN report';

    public string $description = 'All receipts of imported parts with supplier, document reference and cost.';

    public string $group = 'imported';

    public array $filters = ['part_imported', 'category_imported', 'supplier'];

    public function columns(): array
    {
        return ['transaction_date' => ['Date', 'date'], 'reference_no' => ['Reference', 'ref'], 'part_sku' => ['SKU', 'ref'], 'part_name' => ['Product', 'text'],
            'category_name' => ['Category', 'text'], 'specification' => ['Size / description', 'text'], 'quantity' => ['Qty', 'num'], 'unit_name' => ['Unit', 'text'],
            'supplier' => ['Supplier / source', 'text'], 'document_reference' => ['PO / invoice', 'text'], 'unit_cost' => ['Unit cost', 'num'], 'currency' => ['Cur.', 'text'],
            'reversed' => ['Reversed', 'text'], 'recorded_by' => ['Recorded by', 'text']];
    }

    public function query(Request $request, DateRange $range, User $user): Builder
    {
        $q = DB::table('imported_inventory_transactions as t')->leftJoin('suppliers as s', 's.id', '=', 't.supplier_id')->leftJoin('users as us', 'us.id', '=', 't.created_by')
            ->join('spare_parts as p', 'p.id', '=', 't.spare_part_id')
            ->where('t.type', 'in')->whereBetween('t.transaction_date', [$range->fromDate(), $range->toDate()])
            ->select(['t.transaction_date', 't.reference_no', 't.part_sku', 't.part_name', 't.category_name', 't.specification', 't.quantity_in as quantity', 't.unit_name',
                DB::raw('COALESCE(s.name, t.source) as supplier'), 't.document_reference', 't.unit_cost', 't.currency', DB::raw("CASE WHEN t.is_reversed=1 THEN 'Yes' ELSE '' END as reversed"), 'us.name as recorded_by']);
        self::applyCommon($q, $request, ['spare_part_id' => 't.spare_part_id', 'category_id' => 'p.category_id', 'supplier_id' => 't.supplier_id']);

        return $q;
    }

    public function searchable(): array
    {
        return ['t.reference_no', 't.part_sku', 't.part_name', 't.document_reference', 's.name', 't.source'];
    }

    public function defaultSort(): array
    {
        return ['transaction_date', 'desc'];
    }

    public function totals(): array
    {
        return ['quantity'];
    }
}
