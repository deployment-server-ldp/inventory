<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\ImportedInventoryTransaction;
use App\Models\MachineAssembly;
use App\Models\MachineAssemblyItem;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

class ImportedInventoryService
{
    public function __construct(private StockService $stock) {}

    public function receive(array $data, ?string $idempotencyKey = null): array
    {
        return Idempotency::run(ImportedInventoryTransaction::class, $idempotencyKey, function () use ($data, $idempotencyKey) {
            return DB::transaction(function () use ($data, $idempotencyKey) {
                $txn = $this->stock->post('imported', (int) $data['spare_part_id'], 'in', (float) $data['quantity'], [
                    'transaction_date' => $data['transaction_date'],
                    'specification' => $data['specification'] ?? null,
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'source' => $data['source'] ?? null,
                    'document_reference' => $data['document_reference'] ?? null,
                    'unit_cost' => $data['unit_cost'] ?? null,
                    'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null,
                    'remarks' => $data['remarks'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                ]);
                ActivityLogger::log('inventory.in', "Inventory IN {$txn->reference_no}: +".Qty::fmt($txn->quantity_in)." {$txn->part_sku} (balance ".Qty::fmt($txn->balance_after).')',
                    $txn, [], $txn->only(['spare_part_id', 'quantity_in', 'balance_after', 'supplier_id', 'document_reference']), 'imported');

                return $txn;
            });
        });
    }

    public function issue(array $data, ?string $idempotencyKey = null): array
    {
        return Idempotency::run(ImportedInventoryTransaction::class, $idempotencyKey, function () use ($data, $idempotencyKey) {
            return DB::transaction(function () use ($data, $idempotencyKey) {
                $assembly = null;
                if (! empty($data['machine_assembly_id'])) {
                    $assembly = MachineAssembly::query()->lockForUpdate()->findOrFail($data['machine_assembly_id']);
                    if (! $assembly->isOpen()) {
                        throw new StockException("Assembly {$assembly->reference_no} is {$assembly->status}; parts can no longer be issued to it.");
                    }
                }

                $txn = $this->stock->post('imported', (int) $data['spare_part_id'], 'out', (float) $data['quantity'], [
                    'transaction_date' => $data['transaction_date'],
                    'specification' => $data['specification'] ?? null,
                    'machinery_model_id' => $data['machinery_model_id'] ?? ($assembly?->machinery_model_id),
                    'purpose' => $data['purpose'] ?? null,
                    'collected_by' => $data['collected_by'],
                    'department' => $data['department'] ?? null,
                    'machine_assembly_id' => $assembly?->id,
                    'remarks' => $data['remarks'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                ]);

                if ($assembly) {
                    // Unplanned parts are added to the bill of materials with planned qty 0 so they appear in the assembly view.
                    MachineAssemblyItem::firstOrCreate(
                        ['machine_assembly_id' => $assembly->id, 'spare_part_id' => $txn->spare_part_id],
                        ['planned_quantity' => 0, 'remarks' => 'Unplanned — added by issue '.$txn->reference_no]
                    );
                    if ($assembly->status === 'planned') {
                        $assembly->update(['status' => 'in_progress']);
                    }
                }

                ActivityLogger::log('inventory.out', "Inventory OUT {$txn->reference_no}: −".Qty::fmt($txn->quantity_out)." {$txn->part_sku} to {$txn->collected_by}".($assembly ? " for assembly {$assembly->reference_no}" : '').' (balance '.Qty::fmt($txn->balance_after).')',
                    $txn, [], $txn->only(['spare_part_id', 'quantity_out', 'balance_after', 'machinery_model_id', 'machine_assembly_id', 'collected_by']), 'imported');

                return $txn;
            });
        });
    }

    /** Net quantity issued per part for an assembly (OUT − reversed OUTs). */
    public function assemblyIssued(int $assemblyId): array
    {
        return ImportedInventoryTransaction::query()
            ->where('machine_assembly_id', $assemblyId)
            ->where('type', 'out')->where('is_reversed', false)
            ->groupBy('spare_part_id')
            ->selectRaw('spare_part_id, SUM(quantity_out) AS qty')
            ->pluck('qty', 'spare_part_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
