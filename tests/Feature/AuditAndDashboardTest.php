<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CncProductionCompletion;
use App\Models\CncProductionRecord;
use App\Models\ImportedInventoryTransaction;
use App\Models\SparePart;
use App\Services\StockService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\CreatesParts;
use Tests\TestCase;

class AuditAndDashboardTest extends TestCase
{
    use CreatesParts;

    /** Acceptance 11 */
    public function test_stock_changes_and_reversals_appear_in_audit_log(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $part = $this->createImportedPartViaHttp();
        $this->post(route('imported.in.store'), ['transaction_date' => now()->toDateString(), 'spare_part_id' => $part->id, 'quantity' => 30, 'idempotency_key' => (string) Str::uuid()]);
        $this->post(route('imported.out.store'), ['transaction_date' => now()->toDateString(), 'spare_part_id' => $part->id, 'quantity' => 5, 'purpose' => 'Test', 'collected_by' => 'Ali', 'idempotency_key' => (string) Str::uuid()]);
        $out = ImportedInventoryTransaction::where('type', 'out')->first();
        $this->post(route('imported.transactions.reverse', $out), ['reason' => 'Wrong product issued']);
        $this->post(route('adjustments.store', 'imported'), ['spare_part_id' => $part->id, 'adjustment_date' => now()->toDateString(), 'direction' => 'out', 'quantity' => 2, 'reason' => 'Damaged in storage'])->assertSessionHasNoErrors();
        $this->post(route('adjustments.store', 'imported'), ['spare_part_id' => $part->id, 'adjustment_date' => now()->toDateString(), 'direction' => 'out', 'quantity' => 2])->assertSessionHasErrors('reason');

        foreach (['part.created', 'inventory.in', 'inventory.out', 'stock.reversal', 'stock.adjustment'] as $action) {
            $log = ActivityLog::where('action', $action)->latest('id')->first();
            $this->assertNotNull($log, "missing audit entry {$action}");
            $this->assertSame($admin->id, $log->user_id);
        }
        $rev = ActivityLog::where('action', 'stock.reversal')->first();
        $this->assertStringContainsString($out->reference_no, $rev->description);
        $this->assertStringContainsString('Wrong product issued', $rev->description);
        $this->assertSame(28.0, (float) $part->fresh()->current_stock);

        // CNC side: production modification and completion reversal
        $cnc = $this->createCncPartViaHttp();
        $this->post(route('cnc.production.store'), $this->productionPayload($cnc, 3, 20));
        $rec = CncProductionRecord::first();
        $this->put(route('cnc.production.update', $rec), array_merge($this->productionPayload($cnc, 3, 25), ['remarks' => 'corrected']))->assertSessionHasNoErrors();
        $upd = ActivityLog::where('action', 'production.updated')->first();
        $this->assertEquals(20, $upd->old_values['quantity']);
        $this->assertEquals(25, $upd->new_values['quantity']);
        $this->post(route('cnc.completions.store'), ['completion_date' => now()->toDateString(), 'spare_part_id' => $cnc->id, 'quantity_accepted' => 25, 'quantity_rejected' => 0, 'approve_now' => 1, 'idempotency_key' => (string) Str::uuid()]);
        $c = CncProductionCompletion::first();
        $this->post(route('cnc.completions.reverse', $c), ['reason' => 'Batch recalled'])->assertSessionHasNoErrors();
        $this->assertSame(0.0, (float) $cnc->fresh()->current_stock);
        $this->assertTrue(ActivityLog::where('action', 'completion.reversed')->exists());
        $this->assertTrue(ActivityLog::where('action', 'stock.reversal')->where('module', 'cnc')->exists());

        $this->get(route('admin.activity.index', ['q' => $out->reference_no]))->assertOk()->assertSee('Wrong product issued');
        $this->assertSame([], app(StockService::class)->verify());
    }

    /** Acceptance 12 */
    public function test_dashboard_totals_match_ledger(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->actingAs($this->admin());

        $this->assertSame([], app(StockService::class)->verify(), 'every stored balance equals its ledger');

        foreach (['cnc' => 'cnc_inventory_transactions', 'imported' => 'imported_inventory_transactions'] as $type => $table) {
            $ledger = (float) DB::table($table)->join('spare_parts', 'spare_parts.id', '=', "{$table}.spare_part_id")->where('spare_parts.is_active', true)
                ->selectRaw('SUM(quantity_in) - SUM(quantity_out) AS b')->value('b');
            $stored = (float) SparePart::type($type)->where('is_active', true)->sum('current_stock');
            $this->assertEqualsWithDelta($ledger, $stored, 0.0005, "{$type} ledger vs stock");
        }

        $cncUnits = (float) SparePart::type('cnc')->where('is_active', true)->sum('current_stock');
        $impUnits = (float) SparePart::type('imported')->where('is_active', true)->sum('current_stock');
        $view = $this->get(route('dashboard'))->assertOk();
        $kpi = $view->viewData('kpi');
        $this->assertEqualsWithDelta($cncUnits, $kpi['cnc_units'], 0.0005);
        $this->assertEqualsWithDelta($impUnits, $kpi['imp_units'], 0.0005);

        $monthStart = now()->startOfMonth()->toDateString();
        $today = now()->toDateString();
        $inMonth = (float) ImportedInventoryTransaction::where('type', 'in')->where('is_reversed', false)->whereBetween('transaction_date', [$monthStart, $today])->sum('quantity_in');
        $this->assertEqualsWithDelta($inMonth, $kpi['in_month'], 0.0005);

        $imp = $this->get(route('imported.dashboard'))->assertOk()->viewData('kpi');
        $this->assertEqualsWithDelta($impUnits, $imp['units'], 0.0005);

        // stock report totals equal the part balances
        $report = $this->get(route('reports.show', ['report' => 'imported-stock', 'period' => 'year']))->assertOk();
        $this->assertEqualsWithDelta($impUnits, (float) $report->viewData('totals')['stock'], 0.0005);
    }
}
