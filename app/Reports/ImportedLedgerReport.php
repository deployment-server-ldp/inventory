<?php

namespace App\Reports;

class ImportedLedgerReport extends CncLedgerReport
{
    public string $key = 'imported-ledger';

    public string $title = 'Imported inventory ledger (date range)';

    public string $description = 'Every imported stock movement: opening, IN, OUT, adjustments and reversals.';

    public string $group = 'imported';

    public array $filters = ['part_imported', 'ledger_type_imported'];

    protected string $table = 'imported_inventory_transactions';
}
