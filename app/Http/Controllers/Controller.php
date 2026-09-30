<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Apply whitelisted ?sort=&dir= ordering.
     *
     * @param  array<string,string>  $columns  request key => SQL column/expression
     */
    protected function applySort(EloquentBuilder|QueryBuilder $query, Request $request, array $columns, string $defaultKey, string $defaultDir = 'desc'): void
    {
        $key = $request->query('sort');
        $dir = $request->query('dir') === 'asc' ? 'asc' : ($request->query('dir') === 'desc' ? 'desc' : null);
        if (! is_string($key) || ! isset($columns[$key])) {
            $key = $defaultKey;
            $dir = $defaultDir;
        }
        $query->orderBy($columns[$key], $dir ?? 'asc');
    }

    protected function perPage(Request $request): int
    {
        $pp = (int) $request->query('per_page', config('spims.per_page'));

        return in_array($pp, [10, 25, 50, 100], true) ? $pp : (int) config('spims.per_page');
    }

    protected static function like(?string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim((string) $term)).'%';
    }
}
