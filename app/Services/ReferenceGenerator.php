<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Gap-free, sequential reference numbers per prefix and year, e.g. IMP-IN-2026-000042.
 * The counter row is locked FOR UPDATE, so concurrent requests never receive the same number.
 */
class ReferenceGenerator
{
    public static function next(string $prefix, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($prefix, $year) {
            DB::table('document_sequences')->insertOrIgnore(['prefix' => $prefix, 'year' => $year, 'last_number' => 0]);
            $row = DB::table('document_sequences')->where(['prefix' => $prefix, 'year' => $year])->lockForUpdate()->first();
            $next = $row->last_number + 1;
            DB::table('document_sequences')->where(['prefix' => $prefix, 'year' => $year])->update(['last_number' => $next]);

            return sprintf('%s-%d-%06d', $prefix, $year, $next);
        });
    }
}
