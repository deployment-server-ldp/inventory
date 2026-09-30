<?php

namespace Tests\Feature\Concerns;

use App\Models\Machine;
use App\Models\Operation;
use App\Models\Operator;
use App\Models\PartCategory;
use App\Models\SparePart;
use App\Models\Unit;
use Illuminate\Support\Str;

trait CreatesParts
{
    protected function createCncPartViaHttp(array $overrides = []): SparePart
    {
        $this->post(route('cnc.parts.store'), array_merge([
            'sku' => 'CNC-T-'.uniqid(), 'name' => 'Test shaft', 'category_id' => PartCategory::where('scope', 'cnc')->value('id'),
            'unit_id' => Unit::where('symbol', 'pcs')->value('id'), 'final_operation_id' => Operation::where('sequence', 3)->value('id'),
            'min_stock' => 5, 'opening_stock' => 0, 'is_active' => 1, 'primary_image' => $this->image(),
        ], $overrides))->assertSessionHasNoErrors()->assertRedirect();

        return SparePart::latest('id')->firstOrFail();
    }

    protected function createImportedPartViaHttp(array $overrides = []): SparePart
    {
        $this->post(route('imported.products.store'), array_merge([
            'sku' => 'IMP-T-'.uniqid(), 'name' => 'Solenoid valve', 'category_id' => PartCategory::where('scope', 'imported')->value('id'),
            'unit_id' => Unit::where('symbol', 'pcs')->value('id'), 'brand' => 'SMC', 'part_number' => 'SY5120', 'min_stock' => 10,
            'unit_cost' => 120, 'currency' => 'AED', 'is_active' => 1, 'primary_image' => $this->image(),
        ], $overrides))->assertSessionHasNoErrors()->assertRedirect();

        return SparePart::latest('id')->firstOrFail();
    }

    protected function operator(): Operator
    {
        return Operator::firstOrCreate(['employee_code' => 'T-OP-1'], ['name' => 'Test Operator']);
    }

    protected function productionPayload(SparePart $part, int $sequence, float $qty, array $extra = []): array
    {
        return array_merge([
            'production_date' => now()->toDateString(), 'machine_id' => Machine::where('code', 'M-1')->value('id'), 'spare_part_id' => $part->id,
            'operation_id' => Operation::where('sequence', $sequence)->value('id'), 'start_time' => '08:00', 'end_time' => '10:30',
            'quantity' => $qty, 'operator_id' => $this->operator()->id, 'idempotency_key' => (string) Str::uuid(),
        ], $extra);
    }
}
