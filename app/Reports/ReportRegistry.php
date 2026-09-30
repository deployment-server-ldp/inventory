<?php

namespace App\Reports;

class ReportRegistry
{
    public const REPORTS = [
        CncProductionDailyReport::class, CncProductionMonthlyReport::class, CncMachineWiseReport::class, CncOperatorWiseReport::class,
        CncPartWiseReport::class, CncStockReport::class, CncLedgerReport::class,
        ImportedStockReport::class, ImportedInDailyReport::class, ImportedOutDailyReport::class, ImportedLedgerReport::class,
        MachineConsumptionReport::class, AssemblyConsumptionReport::class, StockValuationReport::class,
        CategoryStockReport::class, LowStockReport::class,
    ];

    /** @return array<string, Report> */
    public static function all(): array
    {
        $out = [];
        foreach (self::REPORTS as $class) {
            $r = new $class;
            $out[$r->key] = $r;
        }

        return $out;
    }

    public static function get(string $key): Report
    {
        $all = self::all();
        abort_unless(isset($all[$key]), 404);

        return $all[$key];
    }
}
