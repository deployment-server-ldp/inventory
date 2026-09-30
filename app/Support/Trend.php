<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/** Builds zero-filled daily / weekly / monthly series for charts (MySQL date functions). */
class Trend
{
    public const GRAINS = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];

    public static function grain(?string $grain, DateRange $range): string
    {
        if (isset(self::GRAINS[$grain])) {
            return $grain;
        }
        $days = $range->from->diffInDays($range->to);

        return $days > 120 ? 'monthly' : ($days > 45 ? 'weekly' : 'daily');
    }

    /**
     * @param  array<string,string>  $sums  alias => SQL aggregate expression
     * @return array{labels: string[], series: array<string, float[]>}
     */
    public static function series(Builder $query, string $dateCol, DateRange $range, string $grain, array $sums): array
    {
        $bucketSql = match ($grain) {
            'monthly' => "DATE_FORMAT({$dateCol}, '%Y-%m')",
            'weekly' => "DATE_FORMAT(DATE_SUB({$dateCol}, INTERVAL WEEKDAY({$dateCol}) DAY), '%Y-%m-%d')",
            default => "DATE_FORMAT({$dateCol}, '%Y-%m-%d')",
        };
        $select = ["{$bucketSql} AS bucket"];
        foreach ($sums as $alias => $expr) {
            $select[] = "{$expr} AS {$alias}";
        }
        $rows = (clone $query)->reorder()->selectRaw(implode(', ', $select))->groupByRaw($bucketSql)->get()->keyBy('bucket');

        $labels = [];
        $series = array_fill_keys(array_keys($sums), []);
        $cursor = match ($grain) {
            'monthly' => $range->from->startOfMonth(),
            'weekly' => $range->from->startOfWeek(CarbonImmutable::MONDAY),
            default => $range->from->startOfDay(),
        };
        $guard = 0;
        while ($cursor->lte($range->to) && $guard++ < 800) {
            $key = $grain === 'monthly' ? $cursor->format('Y-m') : $cursor->format('Y-m-d');
            $labels[] = match ($grain) {
                'monthly' => $cursor->format('M Y'),
                'weekly' => 'Wk '.$cursor->format('d M'),
                default => $cursor->format('d M'),
            };
            foreach ($sums as $alias => $_) {
                $series[$alias][] = round((float) ($rows[$key]->{$alias} ?? 0), 3);
            }
            $cursor = match ($grain) {
                'monthly' => $cursor->addMonthNoOverflow(),
                'weekly' => $cursor->addWeek(),
                default => $cursor->addDay(),
            };
        }

        return ['labels' => $labels, 'series' => $series];
    }
}
