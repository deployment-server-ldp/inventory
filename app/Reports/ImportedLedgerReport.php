<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportedLedgerReport extends CncLedgerReport
{
    public string $key = 'imported-ledger';
    public string $title = 'Imported inventory ledger (date range)';
    public string $description = 'Every imported stock movement: opening, IN, OUT, adjustments and reversals.';
    public string $group = 'imported';
    public array $filters = ['part_imported', 'ledger_type_imported'];
    protected string $table = 'imported_inventory_transactions';
}
