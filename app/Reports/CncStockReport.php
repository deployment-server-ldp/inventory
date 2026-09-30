<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

class CncStockReport extends Report
{
    public string $key = 'cnc-stock';

    public string $title = 'CNC inventory stock report';

    public string $description = 'Finished CNC stock per part with receipts and issues in the selected period.';

    public string $group = 'cnc';

    public array $filters = ['category_cnc', 'stock'];

    public function columns(): array
    {
        return ['sku' => ['SKU', 'ref'], 'name' => ['Part', 'text'], 'category' => ['Category', 'text'], 'unit' => ['Unit', 'text'], 'min_stock' => ['Min', 'num'],
            'received' => ['Received in period', 'num'], 'issued' => ['Out in period', 'num'], 'stock' => ['Current stock', 'num'], 'status' => ['Status', 'text']];
    }

    public function query(Request $request, DateRange $range, User $user): Builder
    {
        return StockQuery::build('cnc', 'cnc_inventory_transactions', $request, $range);
    }

    public function searchable(): array
    {
        return ['p.sku', 'p.name', 'c.name'];
    }

    public function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    public function totals(): array
    {
        return ['received', 'issued', 'stock'];
    }
}
