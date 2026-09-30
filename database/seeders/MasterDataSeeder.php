<?php

namespace Database\Seeders;

use App\Models\Machine;
use App\Models\Operation;
use App\Models\PartCategory;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/** Base master data required by the business (idempotent, never overwrites edits). */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            Machine::firstOrCreate(['code' => "M-{$i}"], ['name' => "M-{$i}", 'status' => 'active', 'sort_order' => $i]);
        }

        foreach ([1 => '1st Operation', 2 => '2nd Operation', 3 => '3rd Operation'] as $seq => $name) {
            Operation::firstOrCreate(['sequence' => $seq], ['name' => $name, 'is_active' => true]);
        }

        foreach ([
            ['Piece', 'pcs', false], ['Set', 'set', false], ['Box', 'box', false], ['Pair', 'pair', false],
            ['Meter', 'm', true], ['Kilogram', 'kg', true], ['Roll', 'roll', false],
        ] as [$name, $symbol, $dec]) {
            Unit::firstOrCreate(['symbol' => $symbol], ['name' => $name, 'allows_decimal' => $dec]);
        }

        foreach (['Pneumatic', 'Electrical', 'Mechanical', 'Sensors', 'Valves', 'Bearings'] as $name) {
            PartCategory::firstOrCreate(['name' => $name, 'scope' => 'imported']);
        }
        foreach (['Shafts', 'Gears', 'Cams', 'Knives & Blades', 'Bushes', 'Rollers', 'Drums', 'Brackets', 'Other Machined Parts'] as $name) {
            PartCategory::firstOrCreate(['name' => $name, 'scope' => 'cnc']);
        }
    }
}
