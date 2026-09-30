<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\CncInventoryTransaction;
use App\Models\ImportedInventoryTransaction;
use App\Models\SparePart;
use App\Models\StockAdjustment;
use App\Support\Qty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY place where spare_parts.current_stock is changed.
 *
 * Every posting: (1) locks the part row FOR UPDATE, (2) verifies the part belongs to the ledger's
 * inventory stream, (3) computes the new balance and refuses negatives, (4) writes an immutable
 * ledger row with balance_after, (5) updates current_stock — all in one DB transaction.
 */
class StockService
{
    public const PREFIXES = [
        'cnc' => ['opening' => 'CNC-OPN', 'production_receipt' => 'CNC-RCP', 'issue' => 'CNC-ISS', 'adjustment_in' => 'CNC-ADJ', 'adjustment_out' => 'CNC-ADJ', 'reversal' => 'CNC-REV'],
        'imported' => ['opening' => 'IMP-OPN', 'in' => 'IMP-IN', 'out' => 'IMP-OUT', 'adjustment_in' => 'IMP-ADJ', 'adjustment_out' => 'IMP-ADJ', 'reversal' => 'IMP-REV'],
    ];

    private const INBOUND = ['opening', 'production_receipt', 'in', 'adjustment_in'];

    /**
     * Post a movement. For type "reversal" pass quantity_in / quantity_out explicitly in $attrs.
     */
    public function post(string $stream, int $partId, string $type, float $quantity, array $attrs = []): CncInventoryTransaction|ImportedInventoryTransaction
    {
        if (! isset(self::PREFIXES[$stream][$type])) {
            throw new StockException("Transaction type [{$type}] is not valid for the {$stream} inventory.");
        }
        $quantity = Qty::round($quantity);
        if ($quantity <= 0) {
            throw new StockException('Quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($stream, $partId, $type, $quantity, $attrs) {
            /** @var SparePart $part */
            $part = SparePart::query()->lockForUpdate()->findOrFail($partId);
            $part->load(['unit', 'category']);

            if ($part->inventory_type !== $stream) {
                throw new StockException("{$part->sku} is not a ".SparePart::TYPES[$stream].' item and cannot be posted to this inventory.');
            }

            if ($type === 'reversal') {
                $in = Qty::round((float) ($attrs['quantity_in'] ?? 0));
                $out = Qty::round((float) ($attrs['quantity_out'] ?? 0));
            } else {
                $inbound = in_array($type, self::INBOUND, true);
                $in = $inbound ? $quantity : 0.0;
                $out = $inbound ? 0.0 : $quantity;
            }

            $current = (float) $part->current_stock;
            $new = Qty::round($current + $in - $out);
            if ($new < 0) {
                throw new StockException(sprintf(
                    'Insufficient stock for %s (%s): available %s %s, requested %s. Stock cannot become negative.',
                    $part->name, $part->sku, Qty::fmt($current), $part->unit?->symbol, Qty::fmt($out)
                ));
            }

            $model = $stream === 'cnc' ? new CncInventoryTransaction : new ImportedInventoryTransaction;
            $attrs = array_diff_key($attrs, array_flip(['quantity_in', 'quantity_out']));
            $model->fill($attrs + [
                'transaction_date' => now()->toDateString(),
                'created_by' => Auth::id(),
            ]);
            $model->fill([
                'reference_no' => $attrs['reference_no'] ?? ReferenceGenerator::next(self::PREFIXES[$stream][$type]),
                'spare_part_id' => $part->id,
                'part_name' => $part->name,
                'part_sku' => $part->sku,
                'unit_name' => $part->unit?->name,
                'type' => $type,
                'quantity_in' => $in,
                'quantity_out' => $out,
                'balance_after' => $new,
            ]);
            if ($model instanceof ImportedInventoryTransaction) {
                $model->category_name = $part->category?->name;
                $model->specification ??= $part->specification;
            }
            $model->save();

            $part->current_stock = $new;
            $part->save();

            return $model;
        });
    }

    /**
     * Reverse an IN / OUT / ISSUE (or a production receipt, via the completion workflow).
     */
    public function reverse(string $stream, int $transactionId, string $reason, array $extra = []): CncInventoryTransaction|ImportedInventoryTransaction
    {
        $class = $stream === 'cnc' ? CncInventoryTransaction::class : ImportedInventoryTransaction::class;

        return DB::transaction(function () use ($stream, $class, $transactionId, $reason, $extra) {
            /** @var CncInventoryTransaction|ImportedInventoryTransaction $original */
            $original = $class::query()->lockForUpdate()->findOrFail($transactionId);
            $allowed = $stream === 'cnc' ? ['issue', 'production_receipt'] : ['in', 'out'];
            if (! in_array($original->type, $allowed, true)) {
                throw new StockException('Only '.implode(' / ', $allowed).' transactions can be reversed. Use a stock adjustment for other corrections.');
            }
            if ($original->is_reversed) {
                throw new StockException("Transaction {$original->reference_no} has already been reversed.");
            }

            $reversal = $this->post($stream, $original->spare_part_id, 'reversal', max((float) $original->quantity_in, (float) $original->quantity_out), $extra + [
                'quantity_in' => $original->quantity_out,
                'quantity_out' => $original->quantity_in,
                'reversal_of_id' => $original->id,
                'transaction_date' => now()->toDateString(),
                'remarks' => "Reversal of {$original->reference_no}: {$reason}",
                'machine_assembly_id' => $original->machine_assembly_id ?? null,
            ]);

            $original->is_reversed = true;
            $original->save();

            ActivityLogger::log('stock.reversal', "Reversed {$original->reference_no} ({$original->part_sku}) — {$reason}", $reversal,
                ['reference' => $original->reference_no, 'type' => $original->type, 'quantity_in' => $original->quantity_in, 'quantity_out' => $original->quantity_out],
                ['reversal_reference' => $reversal->reference_no, 'reason' => $reason, 'balance_after' => $reversal->balance_after],
                $stream);

            return $reversal;
        });
    }

    public function adjust(string $stream, int $partId, string $direction, float $quantity, string $reason, string $date, ?string $remarks = null): StockAdjustment
    {
        return DB::transaction(function () use ($stream, $partId, $direction, $quantity, $reason, $date, $remarks) {
            $ref = ReferenceGenerator::next($stream === 'cnc' ? 'CNC-ADJ' : 'IMP-ADJ');
            $adjustment = StockAdjustment::create([
                'reference_no' => $ref,
                'inventory_type' => $stream,
                'spare_part_id' => $partId,
                'adjustment_date' => $date,
                'direction' => $direction,
                'quantity' => $quantity,
                'reason' => $reason,
                'remarks' => $remarks,
                'created_by' => Auth::id(),
            ]);
            $txn = $this->post($stream, $partId, $direction === 'in' ? 'adjustment_in' : 'adjustment_out', $quantity, [
                'reference_no' => $ref,
                'stock_adjustment_id' => $adjustment->id,
                'transaction_date' => $date,
                'remarks' => $reason.($remarks ? " — {$remarks}" : ''),
            ]);
            ActivityLogger::log('stock.adjustment', "Stock adjustment {$ref}: {$direction} ".Qty::fmt($quantity)." of {$txn->part_sku} — {$reason}",
                $adjustment, [], ['direction' => $direction, 'quantity' => $quantity, 'reason' => $reason, 'balance_after' => $txn->balance_after], $stream);

            return $adjustment;
        });
    }

    /**
     * Reconcile ledger vs. stored balance. Returns rows where they differ.
     *
     * @return array<int, array{id:int, sku:string, name:string, type:string, stored:float, ledger:float}>
     */
    public function verify(): array
    {
        $issues = [];
        foreach (['cnc' => 'cnc_inventory_transactions', 'imported' => 'imported_inventory_transactions'] as $type => $table) {
            $rows = DB::table('spare_parts as p')
                ->leftJoinSub(DB::table($table)->selectRaw('spare_part_id, SUM(quantity_in) - SUM(quantity_out) AS net')->groupBy('spare_part_id'), 'l', 'l.spare_part_id', '=', 'p.id')
                ->where('p.inventory_type', $type)
                ->get(['p.id', 'p.sku', 'p.name', 'p.current_stock', DB::raw('COALESCE(l.net, 0) AS net')]);
            foreach ($rows as $r) {
                if (abs((float) $r->current_stock - (float) $r->net) > 0.0005) {
                    $issues[] = ['id' => $r->id, 'sku' => $r->sku, 'name' => $r->name, 'type' => $type, 'stored' => (float) $r->current_stock, 'ledger' => (float) $r->net];
                }
            }
            // Other stream's ledger must never reference this stream's parts
            $mixed = DB::table($table.' as t')->join('spare_parts as p', 'p.id', '=', 't.spare_part_id')
                ->where('p.inventory_type', '!=', $type)->count();
            if ($mixed > 0) {
                $issues[] = ['id' => 0, 'sku' => '*', 'name' => "{$mixed} {$type} ledger rows reference parts of the other inventory", 'type' => $type, 'stored' => 0, 'ledger' => $mixed];
            }
        }

        return $issues;
    }
}
