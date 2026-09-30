<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CncLedgerReport extends Report
{
    public string $key = 'cnc-ledger';
    public string $title = 'CNC inventory ledger (date range)';
    public string $description = 'Every CNC stock movement: opening, production receipts, issues, adjustments and reversals.';
    public string $group = 'cnc';
    public array $filters = ['part_cnc', 'ledger_type_cnc'];
    protected string $table = 'cnc_inventory_transactions';

    public function columns(): array
    {
        return ['transaction_date' => ['Date', 'date'], 'reference_no' => ['Reference', 'ref'], 'part_sku' => ['SKU', 'ref'], 'part_name' => ['Part', 'text'],
            'type' => ['Type', 'text'], 'quantity_in' => ['In', 'num'], 'quantity_out' => ['Out', 'num'], 'balance_after' => ['Balance after posting', 'num'],
            'remarks' => ['Remarks', 'text'], 'recorded_by' => ['By', 'text'], 'created_at' => ['Posted at', 'text']];
    }

    public function query(Request $request, DateRange $range, User $user): Builder
    {
        $q = DB::table($this->table.' as t')->leftJoin('users as us', 'us.id', '=', 't.created_by')
            ->whereBetween('t.transaction_date', [$range->fromDate(), $range->toDate()])
            ->select(['t.transaction_date', 't.reference_no', 't.part_sku', 't.part_name', 't.type', 't.quantity_in', 't.quantity_out', 't.balance_after', 't.remarks', 'us.name as recorded_by', 't.created_at', 't.id']);
        if ($p = $request->integer('spare_part_id') ?: $request->integer('part_id')) {
            $q->where('t.spare_part_id', $p);
        }
        if ($t = $request->query('txn_type')) {
            $q->where('t.type', $t);
        }

        return $q;
    }

    public function searchable(): array
    {
        return ['t.reference_no', 't.part_sku', 't.part_name', 't.remarks'];
    }

    public function defaultSort(): array
    {
        return ['transaction_date', 'asc'];
    }

    public function totals(): array
    {
        return ['quantity_in', 'quantity_out'];
    }
}
