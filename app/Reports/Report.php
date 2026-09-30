<?php

namespace App\Reports;

use App\Models\User;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * A report = columns + a filtered query. The same definition drives the on-screen table
 * (search / sort / paginate) and every export, so exports always match what the user filtered.
 */
abstract class Report
{
    public string $key;

    public string $title;

    public string $description = '';

    /** cnc | imported | both */
    public string $group = 'cnc';

    public bool $usesDates = true;

    /** Filters shown on the report page: machine, part, operator, operation, category, model, assembly, inventory_type, stock, txn_status */
    public array $filters = [];

    /** @return array<string, array{0:string,1:string}> key => [label, type(text|num|date|money|ref)] */
    abstract public function columns(): array;

    abstract public function query(Request $request, DateRange $range, User $user): Builder;

    /** SQL expressions searched with LIKE by the search box. */
    public function searchable(): array
    {
        return [];
    }

    public function defaultSort(): array
    {
        return [array_key_first($this->columns()), 'asc'];
    }

    /** Numeric columns totalled in the footer / exports. */
    public function totals(): array
    {
        return [];
    }

    public function permitted(User $user): bool
    {
        return match ($this->group) {
            'cnc' => $user->can('reports.cnc'),
            'imported' => $user->can('reports.imported'),
            default => $user->can('reports.cnc') || $user->can('reports.imported'),
        };
    }

    /** Inventory types the user may see in a "both" report. */
    protected function allowedTypes(User $user, Request $request): array
    {
        $types = array_values(array_filter(['cnc', 'imported'], fn ($t) => $user->can("reports.{$t}")));
        $want = $request->query('inventory_type');

        return in_array($want, $types, true) ? [$want] : $types;
    }

    protected static function applyCommon(Builder $q, Request $request, array $map): void
    {
        foreach ($map as $param => $column) {
            if ($v = $request->integer($param)) {
                $q->where($column, $v);
            }
        }
    }
}
