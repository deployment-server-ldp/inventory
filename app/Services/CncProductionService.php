<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\CncProductionCompletion;
use App\Models\CncProductionRecord;
use App\Models\Machine;
use App\Models\Operation;
use App\Models\Operator;
use App\Models\SparePart;
use App\Support\Qty;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CNC production & completion workflow.
 *
 * Production records track OPERATION PROGRESS only. Finished stock is created exclusively by
 * approved completions, limited to the "awaiting completion" pool:
 *   pool(part) = Σ qty of completed records at the part's final operation
 *              − Σ inspected qty of pending/approved completions
 */
class CncProductionService
{
    public function __construct(private StockService $stock) {}

    public function finalOperationId(SparePart $part): ?int
    {
        return $part->final_operation_id
            ?? Operation::query()->active()->orderByDesc('sequence')->value('id');
    }

    public function awaitingCompletion(int $partId): float
    {
        $produced = (float) CncProductionRecord::query()
            ->where('spare_part_id', $partId)->where('is_final_operation', true)->where('status', 'completed')
            ->sum('quantity');
        $consumed = (float) CncProductionCompletion::query()
            ->where('spare_part_id', $partId)->whereIn('status', ['pending', 'approved'])
            ->sum('quantity_inspected');

        return Qty::round($produced - $consumed);
    }

    /** @return array<int, array{operation_id:int, name:string, sequence:int, quantity:float}> */
    public function operationProgress(int $partId): array
    {
        return CncProductionRecord::query()
            ->where('spare_part_id', $partId)->where('status', 'completed')
            ->selectRaw('operation_id, operation_name, operation_sequence, SUM(quantity) AS qty')
            ->groupBy('operation_id', 'operation_name', 'operation_sequence')
            ->orderBy('operation_sequence')
            ->get()
            ->map(fn ($r) => ['operation_id' => $r->operation_id, 'name' => $r->operation_name, 'sequence' => (int) $r->operation_sequence, 'quantity' => (float) $r->qty])
            ->all();
    }

    public static function durationMinutes(?string $start, ?string $end): ?int
    {
        if (! $start || ! $end) {
            return null;
        }
        $s = Carbon::createFromFormat('H:i', substr($start, 0, 5));
        $e = Carbon::createFromFormat('H:i', substr($end, 0, 5));
        $minutes = $s->diffInMinutes($e, false);
        if ($minutes < 0) {
            $minutes += 24 * 60; // night shift crossing midnight
        }

        return (int) $minutes;
    }

    public function create(array $data, ?string $idempotencyKey = null): CncProductionRecord
    {
        [$record] = Idempotency::run(CncProductionRecord::class, $idempotencyKey, function () use ($data, $idempotencyKey) {
            return DB::transaction(function () use ($data, $idempotencyKey) {
                $part = SparePart::query()->lockForUpdate()->findOrFail($data['spare_part_id']);
                $this->assertReferences($part, $data);
                $operation = Operation::findOrFail($data['operation_id']);

                $record = new CncProductionRecord($this->payload($data, $part, $operation));
                $record->reference_no = ReferenceGenerator::next('CNC-PRD');
                $record->idempotency_key = $idempotencyKey;
                $record->created_by = Auth::id();
                $record->save();

                ActivityLogger::log('production.created', "Production {$record->reference_no}: {$record->part_sku} {$record->operation_name} on machine #{$record->machine_id}, qty ".Qty::fmt($record->quantity)." ({$record->status})",
                    $record, [], $record->only(['production_date', 'machine_id', 'spare_part_id', 'operation_id', 'operator_id', 'start_time', 'end_time', 'quantity', 'status']), 'cnc');

                return $record;
            });
        });

        return $record;
    }

    public function update(CncProductionRecord $record, array $data): CncProductionRecord
    {
        return DB::transaction(function () use ($record, $data) {
            $record = CncProductionRecord::query()->lockForUpdate()->findOrFail($record->id);
            if ($record->status === 'cancelled') {
                throw new StockException('A cancelled production record cannot be edited.');
            }
            $partIds = array_unique([(int) $record->spare_part_id, (int) $data['spare_part_id']]);
            sort($partIds);
            $parts = SparePart::query()->whereIn('id', $partIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $part = $parts[$data['spare_part_id']];
            $this->assertReferences($part, $data, $record);
            $operation = Operation::findOrFail($data['operation_id']);

            $original = $record->getAttributes();
            $payload = $this->payload($data, $part, $operation);
            // keep the historical final-operation snapshot unless part or operation changed
            if ((int) $record->spare_part_id === (int) $part->id && (int) $record->operation_id === (int) $operation->id) {
                $payload['is_final_operation'] = $record->is_final_operation;
            }
            $record->fill($payload);
            $record->updated_by = Auth::id();
            $record->save();

            foreach ($partIds as $pid) {
                $this->assertPoolNotNegative($pid);
            }
            ActivityLogger::logChanges('production.updated', "Production {$record->reference_no} modified", $record, $original, 'cnc');

            return $record;
        });
    }

    public function finish(CncProductionRecord $record, string $endTime, float $quantity, ?string $remarks): CncProductionRecord
    {
        return DB::transaction(function () use ($record, $endTime, $quantity, $remarks) {
            $record = CncProductionRecord::query()->lockForUpdate()->findOrFail($record->id);
            if ($record->status !== 'running') {
                throw new StockException("Production {$record->reference_no} is not running.");
            }
            $original = $record->getAttributes();
            $record->end_time = $endTime;
            $record->duration_minutes = self::durationMinutes($record->start_time, $endTime);
            $record->quantity = $quantity;
            $record->status = 'completed';
            if ($remarks) {
                $record->remarks = trim(($record->remarks ? $record->remarks."\n" : '').$remarks);
            }
            $record->updated_by = Auth::id();
            $record->save();
            ActivityLogger::logChanges('production.finished', "Production {$record->reference_no} finished with qty ".Qty::fmt($quantity), $record, $original, 'cnc');

            return $record;
        });
    }

    public function cancel(CncProductionRecord $record, string $reason): CncProductionRecord
    {
        return DB::transaction(function () use ($record, $reason) {
            SparePart::query()->lockForUpdate()->findOrFail($record->spare_part_id);
            $record = CncProductionRecord::query()->lockForUpdate()->findOrFail($record->id);
            if ($record->status === 'cancelled') {
                throw new StockException("Production {$record->reference_no} is already cancelled.");
            }
            $original = $record->getAttributes();
            $record->status = 'cancelled';
            $record->cancelled_at = now();
            $record->cancelled_by = Auth::id();
            $record->cancel_reason = $reason;
            $record->save();
            $this->assertPoolNotNegative($record->spare_part_id);
            ActivityLogger::logChanges('production.cancelled', "Production {$record->reference_no} cancelled — {$reason}", $record, $original, 'cnc');

            return $record;
        });
    }

    // ---------------------------------------------------------------- completions

    public function submitCompletion(array $data, bool $approveNow, ?string $idempotencyKey = null): CncProductionCompletion
    {
        [$completion] = Idempotency::run(CncProductionCompletion::class, $idempotencyKey, function () use ($data, $approveNow, $idempotencyKey) {
            return DB::transaction(function () use ($data, $approveNow, $idempotencyKey) {
                $part = SparePart::query()->lockForUpdate()->findOrFail($data['spare_part_id']);
                if (! $part->isCnc()) {
                    throw new StockException('Completions can only be recorded for CNC-manufactured parts.');
                }
                $accepted = Qty::round($data['quantity_accepted']);
                $rejected = Qty::round($data['quantity_rejected'] ?? 0);
                $inspected = Qty::round($accepted + $rejected);
                if ($inspected <= 0) {
                    throw new StockException('Accepted + rejected quantity must be greater than zero.');
                }
                $pool = $this->awaitingCompletion($part->id);
                if ($inspected > $pool) {
                    throw new StockException(sprintf('Only %s unit(s) of %s have finished the final operation and are awaiting completion; you entered %s.',
                        Qty::fmt($pool), $part->sku, Qty::fmt($inspected)));
                }

                $completion = CncProductionCompletion::create([
                    'reference_no' => ReferenceGenerator::next('CNC-CMP'),
                    'completion_date' => $data['completion_date'],
                    'spare_part_id' => $part->id,
                    'part_name' => $part->name,
                    'part_sku' => $part->sku,
                    'quantity_inspected' => $inspected,
                    'quantity_accepted' => $accepted,
                    'quantity_rejected' => $rejected,
                    'status' => 'pending',
                    'remarks' => $data['remarks'] ?? null,
                    'submitted_by' => Auth::id(),
                    'idempotency_key' => $idempotencyKey,
                ]);
                ActivityLogger::log('completion.submitted', "Completion {$completion->reference_no} submitted for {$part->sku}: accepted ".Qty::fmt($accepted).', rejected '.Qty::fmt($rejected),
                    $completion, [], $completion->only(['spare_part_id', 'quantity_accepted', 'quantity_rejected', 'completion_date']), 'cnc');

                if ($approveNow) {
                    $completion = $this->approveCompletion($completion, $accepted, $rejected, 'Approved at submission');
                }

                return $completion;
            });
        });

        return $completion;
    }

    public function approveCompletion(CncProductionCompletion $completion, float $accepted, float $rejected, ?string $notes = null): CncProductionCompletion
    {
        return DB::transaction(function () use ($completion, $accepted, $rejected, $notes) {
            SparePart::query()->lockForUpdate()->findOrFail($completion->spare_part_id);
            $completion = CncProductionCompletion::query()->lockForUpdate()->findOrFail($completion->id);
            if ($completion->status !== 'pending') {
                throw new StockException("Completion {$completion->reference_no} is not pending (status: {$completion->status}).");
            }
            $accepted = Qty::round($accepted);
            $rejected = Qty::round($rejected);
            if (abs(($accepted + $rejected) - (float) $completion->quantity_inspected) > 0.0005) {
                throw new StockException('Accepted + rejected must equal the inspected quantity ('.Qty::fmt($completion->quantity_inspected).').');
            }

            $completion->fill([
                'quantity_accepted' => $accepted,
                'quantity_rejected' => $rejected,
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'decision_notes' => $notes,
            ])->save();

            $receipt = null;
            if ($accepted > 0) {
                $receipt = $this->stock->post('cnc', $completion->spare_part_id, 'production_receipt', $accepted, [
                    'completion_id' => $completion->id,
                    'transaction_date' => $completion->completion_date->toDateString(),
                    'remarks' => "Approved completion {$completion->reference_no}",
                ]);
            }
            ActivityLogger::log('completion.approved', "Completion {$completion->reference_no} approved — ".Qty::fmt($accepted).' added to CNC stock'.($receipt ? " ({$receipt->reference_no})" : ''),
                $completion, ['status' => 'pending'], ['status' => 'approved', 'quantity_accepted' => $accepted, 'quantity_rejected' => $rejected, 'balance_after' => $receipt?->balance_after], 'cnc');

            return $completion;
        });
    }

    public function rejectCompletion(CncProductionCompletion $completion, string $reason): CncProductionCompletion
    {
        return DB::transaction(function () use ($completion, $reason) {
            $completion = CncProductionCompletion::query()->lockForUpdate()->findOrFail($completion->id);
            if ($completion->status !== 'pending') {
                throw new StockException("Completion {$completion->reference_no} is not pending.");
            }
            $completion->fill(['status' => 'rejected', 'approved_by' => Auth::id(), 'approved_at' => now(), 'decision_notes' => $reason])->save();
            ActivityLogger::log('completion.rejected', "Completion {$completion->reference_no} rejected — {$reason}", $completion, ['status' => 'pending'], ['status' => 'rejected', 'reason' => $reason], 'cnc');

            return $completion;
        });
    }

    public function reverseCompletion(CncProductionCompletion $completion, string $reason): CncProductionCompletion
    {
        return DB::transaction(function () use ($completion, $reason) {
            SparePart::query()->lockForUpdate()->findOrFail($completion->spare_part_id);
            $completion = CncProductionCompletion::query()->lockForUpdate()->findOrFail($completion->id);
            if ($completion->status !== 'approved') {
                throw new StockException('Only approved completions can be reversed.');
            }
            $receipt = $completion->receipt()->first();
            if ($receipt && ! $receipt->is_reversed) {
                $this->stock->reverse('cnc', $receipt->id, "Completion {$completion->reference_no} reversed: {$reason}");
            }
            $completion->fill(['status' => 'reversed', 'reversed_by' => Auth::id(), 'reversed_at' => now(), 'reversal_reason' => $reason])->save();
            ActivityLogger::log('completion.reversed', "Completion {$completion->reference_no} reversed — {$reason}", $completion, ['status' => 'approved'], ['status' => 'reversed', 'reason' => $reason], 'cnc');

            return $completion;
        });
    }

    // ---------------------------------------------------------------- helpers

    private function payload(array $data, SparePart $part, Operation $operation): array
    {
        $end = $data['end_time'] ?? null;
        $finalOpId = $this->finalOperationId($part);

        return [
            'production_date' => $data['production_date'],
            'machine_id' => $data['machine_id'],
            'spare_part_id' => $part->id,
            'part_name' => $part->name,
            'part_sku' => $part->sku,
            'machinery_model_id' => $data['machinery_model_id'] ?? null,
            'operation_id' => $operation->id,
            'operation_name' => $operation->name,
            'operation_sequence' => $operation->sequence,
            'is_final_operation' => (int) $finalOpId === (int) $operation->id,
            'start_time' => $data['start_time'],
            'end_time' => $end ?: null,
            'duration_minutes' => self::durationMinutes($data['start_time'], $end),
            'quantity' => Qty::round($data['quantity'] ?? 0),
            'operator_id' => $data['operator_id'],
            'remarks' => $data['remarks'] ?? null,
            'status' => $end ? 'completed' : 'running',
        ];
    }

    private function assertReferences(SparePart $part, array $data, ?CncProductionRecord $existing = null): void
    {
        if (! $part->isCnc()) {
            throw new StockException("{$part->sku} is an imported product; production can only be recorded for CNC parts.");
        }
        $sameAsBefore = fn (string $field) => $existing && (int) $existing->{$field} === (int) $data[$field];
        if (! $part->is_active && ! $sameAsBefore('spare_part_id')) {
            throw new StockException("Part {$part->sku} is inactive.");
        }
        $machine = Machine::findOrFail($data['machine_id']);
        if ($machine->status !== 'active' && ! $sameAsBefore('machine_id')) {
            throw new StockException("Machine {$machine->code} is not active ({$machine->status}).");
        }
        $operator = Operator::findOrFail($data['operator_id']);
        if (! $operator->is_active && ! $sameAsBefore('operator_id')) {
            throw new StockException("Operator {$operator->name} is inactive.");
        }
        $op = Operation::findOrFail($data['operation_id']);
        if (! $op->is_active && ! $sameAsBefore('operation_id')) {
            throw new StockException("Operation {$op->name} is inactive.");
        }
    }

    private function assertPoolNotNegative(int $partId): void
    {
        $pool = $this->awaitingCompletion($partId);
        if ($pool < 0) {
            $sku = SparePart::whereKey($partId)->value('sku');
            throw new StockException(sprintf('This change would reduce the final-operation output of %s below the quantity already submitted for completion (short by %s). Reverse or reject the related completion first.',
                $sku, Qty::fmt(-$pool)));
        }
    }
}
