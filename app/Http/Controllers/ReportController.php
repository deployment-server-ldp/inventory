<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\MachineAssembly;
use App\Models\MachineryModel;
use App\Models\Operation;
use App\Models\Operator;
use App\Models\PartCategory;
use App\Models\SparePart;
use App\Models\Supplier;
use App\Reports\Report;
use App\Reports\ReportRegistry;
use App\Services\ActivityLogger;
use App\Services\ExportService;
use App\Support\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $reports = collect(ReportRegistry::all())->filter(fn (Report $r) => $r->permitted($request->user()));

        return view('reports.index', ['groups' => $reports->groupBy('group')]);
    }

    public function show(Request $request, string $report): View
    {
        $r = $this->authorizeReport($request, $report);
        $range = DateRange::fromRequest($request, 'month');
        $query = $this->buildQuery($r, $request, $range);
        $totals = $this->totals($r, $query);
        $this->sort($r, $query, $request);

        return view('reports.show', [
            'report' => $r, 'range' => $range, 'rows' => $query->paginate($this->perPage($request))->withQueryString(), 'totals' => $totals,
            'options' => $this->filterOptions($r),
        ]);
    }

    public function export(Request $request, string $report, ExportService $export): Response
    {
        $r = $this->authorizeReport($request, $report);
        $range = DateRange::fromRequest($request, 'month');
        $query = $this->buildQuery($r, $request, $range);
        $totals = $this->totals($r, $query);
        $this->sort($r, $query, $request);
        $cols = $r->columns();
        $keys = array_keys($cols);

        $rows = (function () use ($query, $keys, $cols, $totals) {
            foreach ($query->cursor() as $row) {
                yield array_map(fn ($k) => self::cell($row->{$k} ?? null, $cols[$k][1]), $keys);
            }
            if ($totals) {
                yield array_map(fn ($k, $i) => $i === 0 ? 'TOTAL' : (array_key_exists($k, $totals) ? (float) $totals[$k] : ''), $keys, array_keys($keys));
            }
        })();

        $format = in_array($request->query('format'), ['xlsx', 'csv', 'pdf'], true) ? $request->query('format') : 'xlsx';
        ActivityLogger::log('report.exported', "Exported '{$r->title}' as ".strtoupper($format), null, [], ['report' => $r->key, 'filters' => $request->except('format')], 'reports');

        return $export->download($format, $r->title, array_column($cols, 0), $rows, $r->key, $this->metaFor($r, $request, $range),
            array_map(fn ($c) => $c[1] === 'num', array_values($cols)));
    }

    // ------------------------------------------------------------------

    private function authorizeReport(Request $request, string $key): Report
    {
        $r = ReportRegistry::get($key);
        abort_unless($r->permitted($request->user()), 403, 'You do not have permission to view this report.');

        return $r;
    }

    private function buildQuery(Report $r, Request $request, DateRange $range): Builder
    {
        $query = $r->query($request, $range, $request->user());
        if (($q = trim((string) $request->query('q'))) !== '' && $r->searchable()) {
            $query->where(function ($w) use ($r, $q) {
                foreach ($r->searchable() as $col) {
                    $w->orWhere($col, 'like', self::like($q));
                }
            });
        }

        return $query;
    }

    private function totals(Report $r, Builder $query): array
    {
        if (! $r->totals()) {
            return [];
        }
        $sub = (clone $query);
        $select = array_map(fn ($k) => "SUM(`{$k}`) as `{$k}`", $r->totals());

        return (array) \Illuminate\Support\Facades\DB::query()->fromSub($sub, 'rpt')->selectRaw(implode(', ', $select))->first();
    }

    private function sort(Report $r, Builder $query, Request $request): void
    {
        [$defKey, $defDir] = $r->defaultSort();
        $key = $request->query('sort');
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        if (! is_string($key) || ! array_key_exists($key, $r->columns())) {
            [$key, $dir] = [$defKey, $defDir];
        }
        $query->orderBy($key, $dir);
    }

    private static function cell(mixed $v, string $type): mixed
    {
        if ($v === null) {
            return '';
        }

        return match ($type) {
            'num' => is_numeric($v) ? round((float) $v, 3) : $v,
            'date' => substr((string) $v, 0, 10),
            default => (string) $v,
        };
    }

    private function metaFor(Report $r, Request $request, DateRange $range): array
    {
        $meta = [];
        if ($r->usesDates) {
            $meta['Period'] = $range->label();
        }
        $labels = [
            'machine_id' => fn ($v) => Machine::find($v)?->code, 'spare_part_id' => fn ($v) => SparePart::find($v)?->sku, 'operator_id' => fn ($v) => Operator::find($v)?->name,
            'operation_id' => fn ($v) => Operation::find($v)?->name, 'category_id' => fn ($v) => PartCategory::find($v)?->name, 'machinery_model_id' => fn ($v) => MachineryModel::find($v)?->name,
            'machine_assembly_id' => fn ($v) => MachineAssembly::find($v)?->reference_no, 'supplier_id' => fn ($v) => Supplier::find($v)?->name,
        ];
        foreach ($labels as $param => $fn) {
            if ($v = $request->integer($param)) {
                $meta[ucfirst(str_replace(['_id', '_'], ['', ' '], $param))] = $fn($v) ?? $v;
            }
        }
        foreach (['status', 'stock', 'alert', 'inventory_type', 'txn_type', 'assembly_status', 'q'] as $p) {
            if ($v = $request->query($p)) {
                $meta[ucfirst(str_replace('_', ' ', $p === 'q' ? 'search' : $p))] = (string) $v;
            }
        }

        return $meta;
    }

    private function filterOptions(Report $r): array
    {
        $f = array_flip($r->filters);
        $o = [];
        if (isset($f['machine'])) {
            $o['machines'] = Machine::ordered()->get();
        }
        if (isset($f['operator'])) {
            $o['operators'] = Operator::orderBy('name')->get();
        }
        if (isset($f['operation'])) {
            $o['operations'] = Operation::ordered()->get();
        }
        if (isset($f['part_cnc'])) {
            $o['parts'] = SparePart::type('cnc')->orderBy('name')->get(['id', 'sku', 'name']);
        }
        if (isset($f['part_imported'])) {
            $o['parts'] = SparePart::type('imported')->orderBy('name')->get(['id', 'sku', 'name']);
        }
        if (isset($f['category_cnc'])) {
            $o['categories'] = PartCategory::forType('cnc')->orderBy('name')->get();
        }
        if (isset($f['category_imported'])) {
            $o['categories'] = PartCategory::forType('imported')->orderBy('name')->get();
        }
        if (isset($f['model'])) {
            $o['models'] = MachineryModel::orderBy('name')->get();
        }
        if (isset($f['assembly'])) {
            $o['assemblies'] = MachineAssembly::orderByDesc('id')->get();
        }
        if (isset($f['supplier'])) {
            $o['suppliers'] = Supplier::orderBy('name')->get();
        }

        return $o;
    }
}
