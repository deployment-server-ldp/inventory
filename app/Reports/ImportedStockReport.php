<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportedStockReport extends CncStockReport
{
    public string $key = 'imported-stock';
    public string $title = 'Imported inventory stock report';
    public string $description = 'Imported stock per product with IN / OUT in the selected period.';
    public string $group = 'imported';
    public array $filters = ['category_imported', 'stock'];

    public function query(Request $request, DateRange $range, User $user): Builder
    {
        return StockQuery::build('imported', 'imported_inventory_transactions', $request, $range);
    }
}
