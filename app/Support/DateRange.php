<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Uniform date-range handling for dashboards, tables and exports.
 * Reads ?period=today|yesterday|week|month|last_month|year|custom and ?from / ?to (Y-m-d).
 */
class DateRange
{
    public const PERIODS = [
        'today' => 'Today', 'yesterday' => 'Yesterday', 'week' => 'This week', 'month' => 'This month',
        'last_month' => 'Last month', 'year' => 'Year to date', 'custom' => 'Custom range',
    ];

    public function __construct(public CarbonImmutable $from, public CarbonImmutable $to, public string $period = 'custom') {}

    public static function fromRequest(Request $request, string $default = 'month'): self
    {
        $period = (string) $request->query('period', '');
        $from = self::parse($request->query('from'));
        $to = self::parse($request->query('to'));

        if ($period === '' && ($from || $to)) {
            $period = 'custom';
        }
        if ($period === 'custom' && ($from || $to)) {
            $from ??= $to;
            $to ??= $from;
            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }

            return new self($from->startOfDay(), $to->endOfDay(), 'custom');
        }

        return self::preset(array_key_exists($period, self::PERIODS) && $period !== 'custom' ? $period : $default);
    }

    public static function preset(string $period): self
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'today' => new self($now->startOfDay(), $now->endOfDay(), 'today'),
            'yesterday' => new self($now->subDay()->startOfDay(), $now->subDay()->endOfDay(), 'yesterday'),
            'week' => new self($now->startOfWeek(), $now->endOfDay(), 'week'),
            'last_month' => new self($now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth(), 'last_month'),
            'year' => new self($now->startOfYear(), $now->endOfDay(), 'year'),
            default => new self($now->startOfMonth(), $now->endOfDay(), 'month'),
        };
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function fromDate(): string
    {
        return $this->from->toDateString();
    }

    public function toDate(): string
    {
        return $this->to->toDateString();
    }

    public function label(): string
    {
        return $this->fromDate() === $this->toDate()
            ? $this->from->format('d M Y')
            : $this->from->format('d M Y').' – '.$this->to->format('d M Y');
    }

    /** Query string parameters to carry this range into links. */
    public function query(): array
    {
        return ['from' => $this->fromDate(), 'to' => $this->toDate(), 'period' => 'custom'];
    }
}
