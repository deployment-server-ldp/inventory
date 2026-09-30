<?php

namespace App\Rules;

use App\Models\SparePart;
use App\Models\Unit;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Whole numbers only, unless the unit (given directly or via the part) allows decimals. */
class QuantityForUnit implements ValidationRule
{
    public function __construct(private ?int $unitId = null, private ?int $partId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return;
        }
        $unitId = $this->unitId ?? ($this->partId ? SparePart::whereKey($this->partId)->value('unit_id') : null);
        $unit = $unitId ? Unit::find($unitId) : null;
        if ($unit && ! $unit->allows_decimal && floor((float) $value) != (float) $value) {
            $fail("The :attribute must be a whole number for unit '{$unit->name}'.");
        }
        if (preg_match('/\.\d{4,}$/', (string) $value)) {
            $fail('The :attribute may have at most 3 decimal places.');
        }
    }
}
