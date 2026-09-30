<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportedOutDailyReport extends Report
{
    public string $key = 'imported-out-daily';

    public string $title = 'Daily imported OUT report';

    public string $description = 'All issues of imported parts with machine / purpose, collector and assembly.';

    public string $group = 'imported';

    public array $filters = ['part_imported', 'category_imported', 'model', 'assembly'];

    public function columns(): array
    {
        return ['transaction_date' => ['Date', 'date'], 'reference_no' => ['Reference', 'ref'], 'part_sku' => ['SKU', 'ref'], 'part_name' => ['Product', 'text'],
            'category_name' => ['Category', 'text'], 'quantity' => ['Qty', 'num'], 'unit_name' => ['Unit', 'text'], 'machine' => ['Machine / purpose', 'text'],
            'assembly' => ['Assembly', 'ref'], 'collected_by' => ['Collected by', 'text'], 'department' => ['Department', 'text'], 'balance_after' => ['Bal. after', 'num'],
            'reversed' => ['Reversed', 'text'], 'recorded_by' => ['Recorded by', 'text']];
    }

    public function query(Request $request, DateRange $range, User $user): Builder
    {
        $q = DB::table('imported_inventory_transactions as t')->leftJoin('machinery_models as mm', 'mm.id', '=', 't.machinery_model_id')
            ->leftJoin('machine_assemblies as a', 'a.id', '=', 't.machine_assembly_id')->leftJoin('users as us', 'us.id', '=', 't.created_by')
            ->join('spare_parts as p', 'p.id', '=', 't.spare_part_id')
            ->where('t.type', 'out')->whereBetween('t.transaction_date', [$range->fromDate(), $range->toDate()])
            ->select(['t.transaction_date', 't.reference_no', 't.part_sku', 't.part_name', 't.category_name', 't.quantity_out as quantity', 't.unit_name',
                DB::raw("TRIM(CONCAT(COALESCE(mm.name,''), CASE WHEN t.purpose IS NOT NULL AND mm.name IS NOT NULL THEN ' · ' ELSE '' END, COALESCE(t.purpose,''))) as machine"),
                'a.reference_no as assembly', 't.collected_by', 't.department', 't.balance_after', DB::raw("CASE WHEN t.is_reversed=1 THEN 'Yes' ELSE '' END as reversed"), 'us.name as recorded_by']);
        self::applyCommon($q, $request, ['spare_part_id' => 't.spare_part_id', 'category_id' => 'p.category_id', 'machinery_model_id' => 't.machinery_model_id', 'machine_assembly_id' => 't.machine_assembly_id']);

        return $q;
    }

    public function searchable(): array
    {
        return ['t.reference_no', 't.part_sku', 't.part_name', 't.collected_by', 'mm.name', 't.purpose', 'a.reference_no'];
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
