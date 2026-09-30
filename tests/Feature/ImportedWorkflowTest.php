<?php

namespace Tests\Feature;

use App\Exceptions\StockException;
use App\Models\ImportedInventoryTransaction;
use App\Models\MachineAssembly;
use App\Models\MachineryModel;
use App\Models\PartCategory;
use App\Services\ImportedInventoryService;
use App\Services\StockService;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\CreatesParts;
use Tests\TestCase;

class ImportedWorkflowTest extends TestCase
{
    use CreatesParts;

    private function receive(int $partId, float $qty, array $extra = [])
    {
        return $this->post(route('imported.in.store'), array_merge(['transaction_date' => now()->toDateString(), 'spare_part_id' => $partId, 'quantity' => $qty,
            'document_reference' => 'INV-001', 'idempotency_key' => (string) Str::uuid()], $extra));
    }

    private function issue(int $partId, float $qty, array $extra = [])
    {
        return $this->post(route('imported.out.store'), array_merge(['transaction_date' => now()->toDateString(), 'spare_part_id' => $partId, 'quantity' => $qty,
            'machinery_model_id' => $this->model()->id, 'collected_by' => 'Ali', 'idempotency_key' => (string) Str::uuid()], $extra));
    }

    private function model(): MachineryModel
    {
        return MachineryModel::firstOrCreate(['name' => 'Molins MK8']);
    }

    /** Acceptance 5, 6, 7 */
    public function test_receive_100_issue_25_then_reject_80(): void
    {
        $this->actingAs($this->userWithRole('combined_user'));
        $part = $this->createImportedPartViaHttp();
        $this->assertNotNull($part->primaryImage);

        $this->receive($part->id, 100)->assertSessionHasNoErrors();
        $this->assertSame(100.0, (float) $part->fresh()->current_stock);
        $in = ImportedInventoryTransaction::where('type', 'in')->first();
        $this->assertMatchesRegularExpression('/^IMP-IN-\d{4}-\d{6}$/', $in->reference_no);

        $this->issue($part->id, 25)->assertSessionHasNoErrors();
        $this->assertSame(75.0, (float) $part->fresh()->current_stock);

        $this->issue($part->id, 80)->assertSessionHasErrors('quantity');
        $this->assertSame(75.0, (float) $part->fresh()->current_stock);
        $this->assertSame(1, ImportedInventoryTransaction::where('type', 'out')->count());

        // The service-level guard also refuses (e.g. two users racing past the form check)
        try {
            app(ImportedInventoryService::class)->issue(['transaction_date' => now()->toDateString(), 'spare_part_id' => $part->id, 'quantity' => 80, 'collected_by' => 'X']);
            $this->fail('Negative stock must be refused');
        } catch (StockException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }
        $this->assertSame(75.0, (float) $part->fresh()->current_stock);
        $this->assertSame([], app(StockService::class)->verify());
    }

    public function test_double_submit_of_out_form_deducts_once(): void
    {
        $this->actingAs($this->admin());
        $part = $this->createImportedPartViaHttp();
        $this->receive($part->id, 10);
        $key = (string) Str::uuid();
        $this->issue($part->id, 4, ['idempotency_key' => $key])->assertSessionHasNoErrors();
        $this->issue($part->id, 4, ['idempotency_key' => $key])->assertSessionHas('info');
        $this->assertSame(6.0, (float) $part->fresh()->current_stock);
    }

    public function test_decimal_quantities_rejected_for_piece_units(): void
    {
        $this->actingAs($this->admin());
        $part = $this->createImportedPartViaHttp();
        $this->receive($part->id, 2.5)->assertSessionHasErrors('quantity');
        $this->assertSame(0.0, (float) $part->fresh()->current_stock);
    }

    /** Acceptance 8 */
    public function test_machine_assembly_consumption_deducts_stock_once(): void
    {
        $this->actingAs($this->admin());
        $valve = $this->createImportedPartViaHttp();
        $bearing = $this->createImportedPartViaHttp(['name' => 'Bearing 6204']);
        $this->receive($valve->id, 20);
        $this->receive($bearing->id, 50);

        $this->post(route('imported.assemblies.store'), ['name' => 'MK8 rebuild', 'machinery_model_id' => $this->model()->id, 'status' => 'planned'])->assertSessionHasNoErrors();
        $asm = MachineAssembly::first();
        $this->post(route('imported.assemblies.items.store', $asm), ['spare_part_id' => $valve->id, 'planned_quantity' => 6])->assertSessionHasNoErrors();
        $this->post(route('imported.assemblies.items.store', $asm), ['spare_part_id' => $bearing->id, 'planned_quantity' => 12])->assertSessionHasNoErrors();

        // issue from the assembly page
        $this->post(route('imported.assemblies.issue', $asm), ['spare_part_id' => $valve->id, 'quantity' => 4, 'transaction_date' => now()->toDateString(), 'collected_by' => 'Team A', 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasNoErrors();
        // and from the standard OUT form, tagged with the assembly
        $this->issue($bearing->id, 12, ['machine_assembly_id' => $asm->id, 'machinery_model_id' => null])->assertSessionHasNoErrors();

        $this->assertSame(16.0, (float) $valve->fresh()->current_stock);
        $this->assertSame(38.0, (float) $bearing->fresh()->current_stock);
        $this->assertSame(2, ImportedInventoryTransaction::where('type', 'out')->where('machine_assembly_id', $asm->id)->count(), 'one OUT per issue, no duplicate deduction');
        $issued = app(ImportedInventoryService::class)->assemblyIssued($asm->id);
        $this->assertSame(4.0, $issued[$valve->id]);
        $this->assertSame(12.0, $issued[$bearing->id]);
        $this->assertSame('in_progress', $asm->fresh()->status);

        $this->get(route('imported.assemblies.show', $asm))->assertOk()->assertSee('Fully issued');
        $this->get(route('reports.show', ['report' => 'assembly-consumption', 'machine_assembly_id' => $asm->id]))->assertOk()->assertSee($valve->sku);

        // reversing an assembly issue restores stock and the assembly actuals
        $out = ImportedInventoryTransaction::where('type', 'out')->where('spare_part_id', $valve->id)->first();
        $this->post(route('imported.transactions.reverse', $out), ['reason' => 'Returned unused'])->assertSessionHasNoErrors();
        $this->assertSame(20.0, (float) $valve->fresh()->current_stock);
        $this->assertArrayNotHasKey($valve->id, app(ImportedInventoryService::class)->assemblyIssued($asm->id));

        // closed assemblies refuse new issues
        $asm->update(['status' => 'completed']);
        $this->post(route('imported.assemblies.issue', $asm), ['spare_part_id' => $valve->id, 'quantity' => 1, 'transaction_date' => now()->toDateString(), 'collected_by' => 'Team A'])
            ->assertSessionHasErrors('stock');
        $this->assertSame([], app(StockService::class)->verify());
    }

    public function test_reversal_rules(): void
    {
        $this->actingAs($this->admin());
        $part = $this->createImportedPartViaHttp();
        $this->receive($part->id, 10);
        $in = ImportedInventoryTransaction::where('type', 'in')->first();
        $this->issue($part->id, 8);

        // cannot reverse the receipt: only 2 left, reversal would make stock negative
        $this->post(route('imported.transactions.reverse', $in), ['reason' => 'Wrong supplier'])->assertSessionHasErrors('stock');
        $this->assertFalse($in->fresh()->is_reversed);

        $out = ImportedInventoryTransaction::where('type', 'out')->first();
        $this->post(route('imported.transactions.reverse', $out), ['reason' => 'Issued in error'])->assertSessionHasNoErrors();
        $this->post(route('imported.transactions.reverse', $out), ['reason' => 'Issued in error'])->assertSessionHasErrors('stock');
        $this->assertSame(10.0, (float) $part->fresh()->current_stock);
        $this->assertSame(1, ImportedInventoryTransaction::where('type', 'reversal')->count());
        $this->assertSame(3, ImportedInventoryTransaction::count(), 'in + out + reversal: history is never deleted');
    }

    public function test_quick_create_product_returns_json_for_picker(): void
    {
        $this->actingAs($this->userWithRole('import_user'));
        $res = $this->postJson(route('imported.products.quick-store'), ['sku' => 'Q-1', 'name' => 'Quick', 'category_id' => PartCategory::where('scope', 'imported')->value('id'),
            'unit_id' => 1, 'is_active' => 1, 'primary_image' => $this->image()])->assertCreated();
        $this->assertSame('Q-1', $res->json('data.sku'));
        $this->assertNotEmpty($res->json('data.thumb'));
        $this->postJson(route('imported.products.quick-store'), ['sku' => 'Q-2', 'name' => 'No image', 'category_id' => 1, 'unit_id' => 1])->assertStatus(422)->assertJsonValidationErrors('primary_image');
    }
}
