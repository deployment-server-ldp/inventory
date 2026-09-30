<?php

namespace Tests\Feature;

use App\Exceptions\StockException;
use App\Http\Controllers\Cnc\MachineController;
use App\Models\CncInventoryTransaction;
use App\Models\CncProductionCompletion;
use App\Models\CncProductionRecord;
use App\Models\Machine;
use App\Services\CncProductionService;
use App\Services\StockService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\CreatesParts;
use Tests\TestCase;

class CncWorkflowTest extends TestCase
{
    use CreatesParts;

    /** Acceptance 1 */
    public function test_cnc_part_requires_an_image_and_can_be_selected_in_production_entry(): void
    {
        $this->actingAs($this->admin());
        $payload = ['sku' => 'CNC-NOIMG', 'name' => 'No image', 'category_id' => 1, 'unit_id' => 1, 'final_operation_id' => 3];
        $this->post(route('cnc.parts.store'), $payload)->assertSessionHasErrors('primary_image');
        $this->assertDatabaseMissing('spare_parts', ['sku' => 'CNC-NOIMG']);

        // wrong file type is rejected
        $this->post(route('cnc.parts.store'), $payload + ['primary_image' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php')])
            ->assertSessionHasErrors('primary_image');

        $part = $this->createCncPartViaHttp(['sku' => 'CNC-SHAFT-1']);
        $this->assertNotNull($part->primaryImage);
        $this->get(route('media.part-image', [$part->primaryImage->id, 'thumb']))->assertOk();

        // appears in the searchable picker with its thumbnail
        $json = $this->getJson(route('lookup.parts', ['cnc', 'q' => 'SHAFT-1']))->assertOk()->json('data');
        $this->assertSame('CNC-SHAFT-1', $json[0]['sku']);
        $this->assertNotEmpty($json[0]['thumb']);

        $this->post(route('cnc.production.store'), $this->productionPayload($part, 1, 40))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cnc_production_records', ['spare_part_id' => $part->id, 'quantity' => 40, 'status' => 'completed', 'part_sku' => 'CNC-SHAFT-1']);
    }

    /** Acceptance 2 — 100 / 80 / 60 through three operations never becomes 240 in stock */
    public function test_multiple_operations_do_not_double_count_stock(): void
    {
        $this->actingAs($this->admin());
        $part = $this->createCncPartViaHttp();
        $this->post(route('cnc.production.store'), $this->productionPayload($part, 1, 100))->assertSessionHasNoErrors();
        $this->post(route('cnc.production.store'), $this->productionPayload($part, 2, 80))->assertSessionHasNoErrors();
        $this->post(route('cnc.production.store'), $this->productionPayload($part, 3, 60))->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) $part->fresh()->current_stock, 'Production entries alone must not add stock');
        $this->assertSame(60.0, app(CncProductionService::class)->awaitingCompletion($part->id));
        $this->assertSame(0, CncInventoryTransaction::where('spare_part_id', $part->id)->count());

        // cannot complete more than the final-operation output
        $this->post(route('cnc.completions.store'), ['completion_date' => now()->toDateString(), 'spare_part_id' => $part->id, 'quantity_accepted' => 61, 'quantity_rejected' => 0, 'approve_now' => 1, 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasErrors('stock');
        $this->assertSame(0.0, (float) $part->fresh()->current_stock);
    }

    /** Acceptance 3 */
    public function test_monthly_production_sheet_per_machine_and_export(): void
    {
        $this->actingAs($this->admin());
        $part = $this->createCncPartViaHttp();
        $machine = Machine::where('code', 'M-1')->first();
        $this->post(route('cnc.production.store'), $this->productionPayload($part, 1, 25));
        $this->post(route('cnc.production.store'), $this->productionPayload($part, 2, 20));

        $this->get(route('cnc.machines.monthly', [$machine, 'month' => now()->format('Y-m')]))
            ->assertOk()->assertSee('Monthly production sheet')->assertSee('45');
        $sheet = MachineController::monthlySheet($machine, now()->toImmutable()->startOfMonth(), now()->toImmutable()->endOfMonth());
        $this->assertSame(45.0, $sheet['total']);

        foreach (['xlsx' => 'spreadsheetml', 'csv' => 'text/csv', 'pdf' => 'application/pdf'] as $fmt => $mime) {
            $res = $this->get(route('cnc.machines.monthly', [$machine, 'month' => now()->format('Y-m'), 'format' => $fmt]))->assertOk();
            $this->assertStringContainsString($mime, (string) $res->headers->get('Content-Type'));
        }
        $this->get(route('cnc.machines.show', $machine))->assertOk()->assertSee($part->sku);
    }

    /** Acceptance 4 */
    public function test_approved_completion_adds_exact_accepted_quantity_to_cnc_stock(): void
    {
        $admin = $this->admin();
        $cncUser = $this->userWithRole('cnc_user');
        $this->actingAs($admin);
        $part = $this->createCncPartViaHttp();
        $this->actingAs($cncUser);
        foreach ([[1, 100], [2, 80], [3, 60]] as [$seq, $qty]) {
            $this->post(route('cnc.production.store'), $this->productionPayload($part, $seq, $qty))->assertSessionHasNoErrors();
        }

        // CNC user submits (cannot self-approve: approve_now is ignored without the permission)
        $this->post(route('cnc.completions.store'), ['completion_date' => now()->toDateString(), 'spare_part_id' => $part->id, 'quantity_accepted' => 57, 'quantity_rejected' => 3, 'approve_now' => 1, 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasNoErrors();
        $completion = CncProductionCompletion::latest('id')->first();
        $this->assertSame('pending', $completion->status);
        $this->assertSame(0.0, (float) $part->fresh()->current_stock);
        $this->post(route('cnc.completions.approve', $completion), ['quantity_accepted' => 57, 'quantity_rejected' => 3])->assertForbidden();

        // pool now 0 — a second submission is refused
        $this->assertSame(0.0, app(CncProductionService::class)->awaitingCompletion($part->id));

        $this->actingAs($admin)->post(route('cnc.completions.approve', $completion), ['quantity_accepted' => 57, 'quantity_rejected' => 3])->assertSessionHasNoErrors();
        $this->assertSame(57.0, (float) $part->fresh()->current_stock);
        $receipt = CncInventoryTransaction::where('completion_id', $completion->id)->first();
        $this->assertSame('production_receipt', $receipt->type);
        $this->assertSame(57.0, (float) $receipt->balance_after);

        // cancelling the final-op production record would orphan the completion → refused
        $finalRecord = CncProductionRecord::where('spare_part_id', $part->id)->where('is_final_operation', true)->first();
        $this->post(route('cnc.production.cancel', $finalRecord), ['reason' => 'Entered twice by mistake'])->assertSessionHasErrors('stock');
        $this->assertSame('completed', $finalRecord->fresh()->status);

        // issue some finished stock, then the completion can only be reversed if stock remains
        $this->post(route('cnc.stock.issue.store'), ['transaction_date' => now()->toDateString(), 'spare_part_id' => $part->id, 'quantity' => 50, 'issued_to' => 'Customer A', 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasNoErrors();
        $this->assertSame(7.0, (float) $part->fresh()->current_stock);
        $this->post(route('cnc.completions.reverse', $completion), ['reason' => 'QC error found'])->assertSessionHasErrors('stock');
        $this->assertSame('approved', $completion->fresh()->status);
    }

    public function test_running_job_is_finished_later_and_counts_only_then(): void
    {
        $this->actingAs($this->admin());
        $part = $this->createCncPartViaHttp();
        $payload = $this->productionPayload($part, 3, 0, ['end_time' => null, 'quantity' => null]);
        $this->post(route('cnc.production.store'), $payload)->assertSessionHasNoErrors();
        $rec = CncProductionRecord::latest('id')->first();
        $this->assertSame('running', $rec->status);
        $this->assertSame(0.0, app(CncProductionService::class)->awaitingCompletion($part->id));
        $this->get(route('cnc.dashboard'))->assertOk()->assertSee('M-1');

        $this->post(route('cnc.production.finish', $rec), ['end_time' => '07:30', 'quantity' => 30])->assertSessionHasNoErrors();
        $rec->refresh();
        $this->assertSame('completed', $rec->status);
        $this->assertSame(1410, $rec->duration_minutes, 'Crossing midnight: 08:00 → 07:30 = 23h30m');
        $this->assertSame(30.0, app(CncProductionService::class)->awaitingCompletion($part->id));
    }

    public function test_duplicate_submission_creates_one_record(): void
    {
        $this->actingAs($this->admin());
        $part = $this->createCncPartViaHttp();
        $payload = $this->productionPayload($part, 1, 10);
        $this->post(route('cnc.production.store'), $payload);
        $this->post(route('cnc.production.store'), $payload);
        $this->assertSame(1, CncProductionRecord::where('spare_part_id', $part->id)->count());
    }

    public function test_imported_part_cannot_be_used_in_cnc_production_or_ledger(): void
    {
        $this->actingAs($this->admin());
        $imp = $this->createImportedPartViaHttp();
        $this->post(route('cnc.production.store'), $this->productionPayload($imp, 1, 10))->assertSessionHasErrors('spare_part_id');
        $this->expectException(StockException::class);
        app(StockService::class)->post('cnc', $imp->id, 'issue', 1);
    }

    public function test_inactive_machine_rejects_new_entries(): void
    {
        $this->actingAs($this->admin());
        $part = $this->createCncPartViaHttp();
        Machine::where('code', 'M-1')->update(['status' => 'maintenance']);
        $this->post(route('cnc.production.store'), $this->productionPayload($part, 1, 10))->assertSessionHasErrors('stock');
        $this->assertSame(0, CncProductionRecord::count());
    }
}
