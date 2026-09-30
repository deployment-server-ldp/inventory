<?php

namespace Tests\Feature;

use App\Models\CncInventoryTransaction;
use App\Models\CncProductionCompletion;
use App\Models\CncProductionRecord;
use App\Models\ImportedInventoryTransaction;
use App\Models\Machine;
use App\Models\MachineAssembly;
use App\Models\SparePart;
use App\Reports\ReportRegistry;
use Database\Seeders\DemoDataSeeder;
use Tests\TestCase;

/** Renders every screen with realistic demo data as Super Admin and checks for server errors. */
class PageSmokeTest extends TestCase
{
    public function test_every_page_renders_with_demo_data(): void
    {
        $this->seed(DemoDataSeeder::class);
        $admin = $this->admin();
        $this->actingAs($admin);

        $cnc = SparePart::type('cnc')->first();
        $imp = SparePart::type('imported')->first();
        $machine = Machine::first();
        $record = CncProductionRecord::first();
        $completion = CncProductionCompletion::first();
        $txn = ImportedInventoryTransaction::where('type', 'out')->first();
        $assembly = MachineAssembly::first();

        $urls = [
            route('dashboard'), route('dashboard', ['inventory_type' => 'cnc', 'period' => 'year']), route('dashboard', ['inventory_type' => 'imported', 'category_id' => $imp->category_id]),
            route('search', ['q' => 'BRG']), route('search', ['q' => 'M-1']), route('password.change'),
            route('cnc.dashboard'), route('cnc.dashboard', ['period' => 'year', 'grain' => 'monthly', 'machine_id' => $machine->id]),
            route('cnc.production.index'), route('cnc.production.index', ['status' => 'all', 'sort' => 'qty', 'dir' => 'asc']),
            route('cnc.production.create'), route('cnc.production.create', ['part' => $cnc->id]), route('cnc.production.show', $record), route('cnc.production.edit', $record),
            route('cnc.progress'), route('cnc.machines.index'), route('cnc.machines.show', $machine), route('cnc.machines.monthly', $machine),
            route('cnc.completions.index'), route('cnc.completions.create'), route('cnc.completions.create', ['part' => $cnc->id]), route('cnc.completions.show', $completion),
            route('cnc.stock.index'), route('cnc.stock.ledger'), route('cnc.stock.ledger', ['part_id' => $cnc->id, 'period' => 'year']), route('cnc.stock.issue'),
            route('cnc.parts.index'), route('cnc.parts.create'), route('cnc.parts.show', $cnc), route('cnc.parts.edit', $cnc),
            route('imported.dashboard'), route('imported.dashboard', ['period' => 'year']), route('imported.in.create'), route('imported.out.create'),
            route('imported.transactions.index'), route('imported.transactions.show', $txn), route('imported.stock.index'),
            route('imported.stock.ledger', ['part_id' => $imp->id, 'period' => 'year']), route('imported.products.index'), route('imported.products.create'),
            route('imported.products.show', $imp), route('imported.products.edit', $imp),
            route('imported.assemblies.index'), route('imported.assemblies.create'), route('imported.assemblies.show', $assembly), route('imported.assemblies.edit', $assembly),
            route('adjustments.index'), route('adjustments.create', 'cnc'), route('adjustments.create', 'imported'),
            route('reports.index'), route('admin.users.index'), route('admin.users.create'), route('admin.users.edit', $admin), route('admin.roles.index'),
            route('admin.roles.edit', 1), route('admin.roles.create'), route('admin.activity.index'), route('admin.activity.show', 1), route('admin.system.health'), route('admin.system.logs'),
            route('admin.company.edit'), route('lookup.parts', 'cnc'), route('lookup.parts', ['imported', 'q' => 'valve']), route('lookup.completion-pool', $cnc),
        ];
        foreach (['machines', 'operators', 'operations', 'categories', 'units', 'machinery-models', 'suppliers'] as $entity) {
            $urls[] = route('settings.index', $entity);
            $urls[] = route('settings.create', $entity);
            $urls[] = route('settings.edit', [$entity, 1]);
        }
        foreach (array_keys(ReportRegistry::all()) as $key) {
            $urls[] = route('reports.show', ['report' => $key, 'period' => 'year']);
            $urls[] = route('reports.show', ['report' => $key, 'period' => 'year', 'q' => 'a', 'sort' => array_key_first(ReportRegistry::get($key)->columns()), 'dir' => 'desc']);
        }

        foreach ($urls as $url) {
            $res = $this->get($url);
            $this->assertSame(200, $res->status(), "GET {$url} returned {$res->status()}: ".($res->exception ? $res->exception->getMessage().' @ '.$res->exception->getFile().':'.$res->exception->getLine() : substr((string) $res->getContent(), 0, 500)));
        }

        // images are served only via the authenticated media route
        $img = $cnc->primaryImage;
        $this->get(route('media.part-image', [$img->id, 'thumb']))->assertOk()->assertHeader('Content-Type', 'image/png');

        // exports
        foreach (['xlsx', 'csv', 'pdf'] as $fmt) {
            $this->get(route('reports.export', ['report' => 'cnc-production-daily', 'format' => $fmt, 'period' => 'month']))->assertOk();
            $this->get(route('cnc.machines.monthly', [$machine, 'format' => $fmt]))->assertOk();
        }
        $this->get(route('reports.export', ['report' => 'imported-out-daily', 'format' => 'xlsx', 'period' => 'year']))->assertOk();

        $this->assertSame([], app(\App\Services\StockService::class)->verify());
        $this->assertGreaterThan(0, CncInventoryTransaction::count());
    }
}
