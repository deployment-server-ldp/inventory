<?php

namespace Database\Seeders;

use App\Models\Machine;
use App\Models\MachineAssembly;
use App\Models\MachineryModel;
use App\Models\Operation;
use App\Models\Operator;
use App\Models\PartCategory;
use App\Models\Role;
use App\Models\SparePart;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\CncProductionService;
use App\Services\ImageService;
use App\Services\ImportedInventoryService;
use App\Services\StockService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * SAMPLE / TEST DATA ONLY — never run on a live production database.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Creates demo operators, machinery models, suppliers, CNC parts and imported products (with generated
 * images), ~6 weeks of production, completions, IN/OUT movements and two assemblies — all posted through
 * the same services the application uses, so every ledger balance is consistent. Also creates one demo user
 * per role with a random password that is printed once.
 */
class DemoDataSeeder extends Seeder
{
    private array $tmpFiles = [];

    public function run(): void
    {
        if (app()->isProduction() && ! $this->command?->confirm('APP_ENV is production. Insert DEMO data into this database?', false)) {
            $this->command?->warn('Demo data NOT inserted.');

            return;
        }
        if (SparePart::where('sku', 'CNC-SH-001')->exists()) {
            $this->command?->warn('Demo data already present — skipping.');

            return;
        }

        $this->call(DatabaseSeeder::class);
        mt_srand(20260930);

        $this->demoUsers();
        $admin = User::whereHas('role', fn ($q) => $q->where('name', Role::SUPER_ADMIN))->orderBy('id')->first();
        Auth::setUser($admin);

        $operators = collect([
            ['OP-001', 'Muhammad Asif'], ['OP-002', 'Imran Khan'], ['OP-003', 'Bilal Ahmed'], ['OP-004', 'Kashif Ali'],
            ['OP-005', 'Usman Tariq'], ['OP-006', 'Faisal Mehmood'], ['OP-007', 'Naveed Iqbal'], ['OP-008', 'Zeeshan Haider'],
        ])->map(fn ($o) => Operator::firstOrCreate(['employee_code' => $o[0]], ['name' => $o[1], 'is_active' => true]));

        $models = collect([
            ['Molins MK8', 'Molins', 'MK8'], ['Molins MK9', 'Molins', 'MK9'], ['Hauni Protos 70', 'Hauni', 'P70'],
            ['Hauni Protos 90', 'Hauni', 'P90'], ['G.D 121', 'G.D', '121'],
        ])->map(fn ($m) => MachineryModel::firstOrCreate(['name' => $m[0]], ['manufacturer' => $m[1], 'model_code' => $m[2]]));

        $suppliers = collect([
            ['Al Noor Industrial Supplies LLC', 'UAE'], ['Gulf Pneumatics Trading', 'UAE'], ['Emirates Electro Parts FZE', 'UAE'],
        ])->map(fn ($s) => Supplier::firstOrCreate(['name' => $s[0]], ['country' => $s[1]]));

        $pcs = Unit::where('symbol', 'pcs')->first();
        $set = Unit::where('symbol', 'set')->first();
        $ops = Operation::ordered()->get();
        $cat = fn (string $name, string $scope) => PartCategory::where('name', $name)->where('scope', $scope)->value('id');

        // ---------------------------------------------------------------- CNC parts
        $cncDefs = [
            ['CNC-SH-001', 'Garniture drive shaft', 'Shafts', 'Ø25 × 180 mm, EN8', 3, [0, 2]],
            ['CNC-SH-002', 'Suction drum shaft', 'Shafts', 'Ø30 × 240 mm, EN24', 3, [2, 3]],
            ['CNC-GR-001', 'Cutter head gear 48T', 'Gears', 'Module 1.5, 48 teeth', 3, [0, 1]],
            ['CNC-GR-002', 'Tipping paper gear 32T', 'Gears', 'Module 1.25, 32 teeth', 2, [3]],
            ['CNC-CM-001', 'Transfer drum cam', 'Cams', 'Hardened, 62 HRC', 3, [0, 1, 2]],
            ['CNC-KN-001', 'Cut-off knife holder', 'Knives & Blades', '110 × 40 × 12 mm', 2, [0, 1]],
            ['CNC-BU-001', 'Bronze bush 20/26', 'Bushes', 'ID 20, OD 26, L 30', 2, [0, 1, 2, 3, 4]],
            ['CNC-RL-001', 'Paper guide roller', 'Rollers', 'Ø40 × 95 mm, SS304', 3, [2, 3]],
            ['CNC-DR-001', 'Filter drum', 'Drums', 'Ø120 × 60 mm, Al 6061', 3, [4]],
            ['CNC-BR-001', 'Tongue bracket', 'Brackets', 'MS plate 8 mm', 2, [0]],
        ];
        $cncParts = collect();
        foreach ($cncDefs as $i => [$sku, $name, $category, $spec, $finalSeq, $modelIdx]) {
            $part = SparePart::create([
                'inventory_type' => 'cnc', 'sku' => $sku, 'name' => $name, 'category_id' => $cat($category, 'cnc'), 'unit_id' => $pcs->id,
                'specification' => $spec, 'description' => "Demo CNC part — {$name}.", 'min_stock' => [10, 20, 15, 25, 5][$i % 5],
                'final_operation_id' => $ops->firstWhere('sequence', $finalSeq)->id, 'is_active' => true, 'created_by' => $admin->id,
            ]);
            $part->machineryModels()->sync(collect($modelIdx)->map(fn ($m) => $models[$m]->id));
            $this->image($part, [30, 64, 175]);
            $cncParts->push($part);
        }

        // ---------------------------------------------------------------- Imported products
        $impDefs = [
            ['IMP-PN-001', 'Pneumatic cylinder 32×50', 'Pneumatic', 'Festo', 'DSNU-32-50-PPV-A', 'pcs', 85.00, 5],
            ['IMP-PN-002', 'Solenoid valve 5/2 24VDC', 'Valves', 'SMC', 'SY5120-5LZD-01', 'pcs', 120.00, 8],
            ['IMP-PN-003', 'Air filter regulator 1/4"', 'Pneumatic', 'Festo', 'LFR-1/4-D-MINI', 'pcs', 95.50, 4],
            ['IMP-EL-001', 'Proximity sensor M12 PNP', 'Sensors', 'Omron', 'E2E-X4MD1', 'pcs', 65.00, 10],
            ['IMP-EL-002', 'Photoelectric sensor', 'Sensors', 'Sick', 'WL12-3P2431', 'pcs', 240.00, 4],
            ['IMP-EL-003', 'Servo drive 1.5 kW', 'Electrical', 'Siemens', '6SL3210-5FE11-5UA0', 'pcs', 2850.00, 1],
            ['IMP-EL-004', 'Contactor 3P 25A 24VDC', 'Electrical', 'Schneider', 'LC1D25BD', 'pcs', 145.00, 6],
            ['IMP-BR-001', 'Deep groove ball bearing 6204-2RS', 'Bearings', 'SKF', '6204-2RS1', 'pcs', 18.50, 40],
            ['IMP-BR-002', 'Needle bearing HK2016', 'Bearings', 'INA', 'HK2016', 'pcs', 12.75, 30],
            ['IMP-MC-001', 'Timing belt HTD 8M-1200', 'Mechanical', 'Gates', '8MGT-1200-20', 'pcs', 210.00, 3],
            ['IMP-MC-002', 'Coupling set (jaw type)', 'Mechanical', 'KTR', 'ROTEX 24', 'set', 175.00, 2],
            ['IMP-VL-001', 'Vacuum ejector valve', 'Valves', 'Piab', 'M10 SI', 'pcs', 330.00, 2],
        ];
        $impParts = collect();
        foreach ($impDefs as $i => [$sku, $name, $category, $brand, $pn, $unit, $cost, $min]) {
            $part = SparePart::create([
                'inventory_type' => 'imported', 'sku' => $sku, 'name' => $name, 'category_id' => $cat($category, 'imported'),
                'unit_id' => $unit === 'set' ? $set->id : $pcs->id, 'brand' => $brand, 'part_number' => $pn, 'specification' => $pn,
                'supplier_id' => $suppliers[$i % 3]->id, 'unit_cost' => $cost, 'currency' => 'AED', 'min_stock' => $min, 'is_active' => true,
                'created_by' => $admin->id,
            ]);
            $part->machineryModels()->sync([$models[$i % 5]->id, $models[($i + 2) % 5]->id]);
            $this->image($part, [5, 122, 85]);
            $impParts->push($part);
        }

        $stock = app(StockService::class);
        $cnc = app(CncProductionService::class);
        $imp = app(ImportedInventoryService::class);

        // opening stock for a few imported items (as if migrated from an old register)
        foreach ($impParts->take(6) as $i => $p) {
            $stock->post('imported', $p->id, 'opening', [12, 20, 6, 30, 5, 2][$i], ['transaction_date' => now()->subDays(60)->toDateString(), 'remarks' => 'Opening stock (demo)']);
        }

        // ---------------------------------------------------------------- 6 weeks of activity
        $machines = Machine::ordered()->where('status', 'active')->get();
        $start = CarbonImmutable::today()->subDays(42);
        for ($d = $start; $d->lt(CarbonImmutable::today()); $d = $d->addDay()) {
            if ($d->isFriday()) {
                continue; // weekly off in this demo
            }
            $date = $d->toDateString();
            // CNC: each day ~8 machines work through operation sequences
            foreach ($machines->random(8) as $mi => $machine) {
                $part = $cncParts[($d->dayOfYear + $mi) % $cncParts->count()];
                $final = $part->finalOperation()->first()->sequence;
                $base = mt_rand(30, 60);
                foreach ($ops->where('sequence', '<=', $final) as $op) {
                    $qty = max(1, $base - ($op->sequence - 1) * mt_rand(2, 8)); // later ops trail earlier ones
                    $startH = 7 + ($op->sequence - 1) * 3;
                    $cnc->create([
                        'production_date' => $date, 'machine_id' => $machine->id, 'spare_part_id' => $part->id,
                        'machinery_model_id' => $part->machineryModels()->value('machinery_models.id'), 'operation_id' => $op->id,
                        'start_time' => sprintf('%02d:%02d', $startH, mt_rand(0, 3) * 15), 'end_time' => sprintf('%02d:%02d', $startH + 2, mt_rand(0, 3) * 15),
                        'quantity' => $qty, 'operator_id' => $operators->random()->id, 'remarks' => null,
                    ]);
                }
            }
            // Completions every 3rd day for parts with output waiting
            if ($d->dayOfYear % 3 === 0) {
                foreach ($cncParts as $part) {
                    $pool = $cnc->awaitingCompletion($part->id);
                    if ($pool >= 10) {
                        $inspected = floor($pool * 0.8);
                        $rejected = floor($inspected * 0.03);
                        $cnc->submitCompletion(['completion_date' => $date, 'spare_part_id' => $part->id, 'quantity_accepted' => $inspected - $rejected, 'quantity_rejected' => $rejected, 'remarks' => 'QC batch (demo)'], true);
                    }
                }
            }
            // CNC dispatches
            if ($d->dayOfYear % 4 === 0) {
                foreach ($cncParts->random(3) as $part) {
                    $avail = (float) $part->fresh()->current_stock;
                    if ($avail >= 5) {
                        $stock->post('cnc', $part->id, 'issue', floor($avail * 0.3), ['transaction_date' => $date, 'issued_to' => 'Customer order (demo)', 'purpose' => 'Sale']);
                    }
                }
            }
            // Imported receipts (weekly shipments from Dubai)
            if ($d->isMonday()) {
                $ref = 'INV-DXB-'.$d->format('ymd');
                foreach ($impParts->random(5) as $p) {
                    $imp->receive(['transaction_date' => $date, 'spare_part_id' => $p->id, 'quantity' => mt_rand(4, 30), 'supplier_id' => $p->supplier_id,
                        'document_reference' => $ref, 'unit_cost' => $p->unit_cost, 'currency' => 'AED', 'remarks' => 'Shipment (demo)']);
                }
            }
            // Imported issues
            foreach ($impParts->random(2) as $p) {
                $avail = (float) $p->fresh()->current_stock;
                if ($avail >= 2) {
                    $imp->issue(['transaction_date' => $date, 'spare_part_id' => $p->id, 'quantity' => mt_rand(1, (int) min(4, $avail)),
                        'machinery_model_id' => $models->random()->id, 'collected_by' => $operators->random()->name, 'department' => 'Maintenance']);
                }
            }
        }

        // Running jobs today (live dashboard)
        foreach ($machines->take(3) as $i => $machine) {
            $part = $cncParts[$i];
            $cnc->create(['production_date' => now()->toDateString(), 'machine_id' => $machine->id, 'spare_part_id' => $part->id, 'operation_id' => $ops->first()->id,
                'start_time' => now()->subHours($i + 1)->format('H:i'), 'end_time' => null, 'quantity' => null, 'operator_id' => $operators[$i]->id]);
        }
        // A pending completion to demonstrate the approval queue
        foreach ($cncParts as $part) {
            $pool = $cnc->awaitingCompletion($part->id);
            if ($pool >= 5) {
                $cnc->submitCompletion(['completion_date' => now()->toDateString(), 'spare_part_id' => $part->id, 'quantity_accepted' => floor($pool / 2), 'quantity_rejected' => 0, 'remarks' => 'Awaiting QC (demo)'], false);
                break;
            }
        }

        // ---------------------------------------------------------------- Assemblies
        $a1 = MachineAssembly::create(['reference_no' => 'ASM-DEMO-001', 'name' => 'Rebuild — Molins MK8 line 2', 'machinery_model_id' => $models[0]->id, 'customer' => 'Internal', 'status' => 'planned',
            'start_date' => now()->subDays(10)->toDateString(), 'target_date' => now()->addDays(20)->toDateString(), 'created_by' => $admin->id]);
        $a2 = MachineAssembly::create(['reference_no' => 'ASM-DEMO-002', 'name' => 'New Protos 70 maker assembly', 'machinery_model_id' => $models[2]->id, 'customer' => 'Demo Tobacco Co.', 'status' => 'planned',
            'start_date' => now()->subDays(5)->toDateString(), 'target_date' => now()->addDays(40)->toDateString(), 'created_by' => $admin->id]);
        foreach ([[$a1, [0 => 4, 1 => 6, 3 => 8, 7 => 12]], [$a2, [2 => 2, 4 => 3, 5 => 1, 6 => 4, 8 => 10, 10 => 2]]] as [$asm, $items]) {
            foreach ($items as $idx => $planned) {
                $asm->items()->create(['spare_part_id' => $impParts[$idx]->id, 'planned_quantity' => $planned]);
            }
        }
        foreach ([0, 1, 7] as $idx) {
            $p = $impParts[$idx]->fresh();
            if ((float) $p->current_stock >= 2) {
                $imp->issue(['transaction_date' => now()->toDateString(), 'spare_part_id' => $p->id, 'quantity' => 2, 'machine_assembly_id' => $a1->id, 'collected_by' => 'Assembly team', 'department' => 'Assembly']);
            }
        }

        foreach ($this->tmpFiles as $f) {
            @unlink($f);
        }
        Auth::logout();
        $this->command?->info('Demo data created. Run `php artisan stock:verify` to confirm ledgers reconcile.');
    }

    private function demoUsers(): void
    {
        $rows = [];
        foreach ([[Role::SUPER_ADMIN, 'demo_admin', 'Demo Super Admin'], [Role::CNC_USER, 'demo_cnc', 'Demo CNC User'],
            [Role::IMPORT_USER, 'demo_import', 'Demo Import User'], [Role::COMBINED_USER, 'demo_combined', 'Demo Combined User']] as [$role, $username, $name]) {
            if (User::where('username', $username)->exists()) {
                continue;
            }
            $password = Str::password(12, symbols: false);
            $this->makeUser($username, $name, $role, $password);
            $rows[] = [$username, $password, $role];
        }
        if ($rows && $this->command) {
            $this->command->warn('Demo accounts (shown once — for TESTING only, deactivate before go-live):');
            $this->command->table(['Username', 'Password', 'Role'], $rows);
        }
    }

    private function makeUser(string $username, string $name, string $role, ?string $password = null): User
    {
        return User::create(['name' => $name, 'username' => $username, 'password' => $password ?? Str::password(16), 'role_id' => Role::where('name', $role)->value('id'), 'is_active' => true]);
    }

    /** Generates a simple labelled placeholder image and stores it through the normal ImageService. */
    private function image(SparePart $part, array $rgb): void
    {
        $img = imagecreatetruecolor(480, 480);
        imagefill($img, 0, 0, imagecolorallocate($img, 244, 246, 250));
        $c = imagecolorallocate($img, ...$rgb);
        imagefilledrectangle($img, 0, 0, 479, 90, $c);
        imagefilledellipse($img, 240, 270, 230, 230, imagecolorallocate($img, 203, 213, 225));
        imagefilledellipse($img, 240, 270, 90, 90, imagecolorallocate($img, 244, 246, 250));
        $white = imagecolorallocate($img, 255, 255, 255);
        imagestring($img, 5, 20, 20, $part->sku, $white);
        imagestring($img, 3, 20, 50, Str::limit($part->name, 55), $white);
        imagestring($img, 2, 20, 455, 'DEMO IMAGE', imagecolorallocate($img, 100, 116, 139));
        $path = tempnam(sys_get_temp_dir(), 'spims').'.png';
        imagepng($img, $path);
        imagedestroy($img);
        $this->tmpFiles[] = $path;
        app(ImageService::class)->store($part, new UploadedFile($path, $part->sku.'.png', 'image/png', null, true), true);
    }
}
