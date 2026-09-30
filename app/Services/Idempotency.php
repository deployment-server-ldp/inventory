<?php

namespace App\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Protects stock-affecting forms against double submission.
 * Each form carries a UUID; the target table has a UNIQUE index on idempotency_key.
 */
class Idempotency
{
    /**
     * @template T of Model
     *
     * @param  class-string<T>  $modelClass
     * @return array{0: T, 1: bool} [model, created]
     */
    public static function run(string $modelClass, ?string $key, Closure $callback): array
    {
        if ($key) {
            $existing = $modelClass::query()->where('idempotency_key', $key)->first();
            if ($existing) {
                return [$existing, false];
            }
        }

        try {
            return [$callback(), true];
        } catch (UniqueConstraintViolationException $e) {
            if ($key && ($existing = $modelClass::query()->where('idempotency_key', $key)->first())) {
                return [$existing, false];
            }
            throw $e;
        }
    }
}
