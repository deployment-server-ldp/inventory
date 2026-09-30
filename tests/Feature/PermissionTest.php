<?php

namespace Tests\Feature;

use App\Models\CncProductionCompletion;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\CreatesParts;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use CreatesParts;

    /** Acceptance 9 */
    public function test_cnc_user_cannot_access_import_pages_or_admin(): void
    {
        $this->actingAs($this->admin());
        $imp = $this->createImportedPartViaHttp();
        $this->actingAs($this->userWithRole(Role::CNC_USER));

        foreach ([
            route('imported.dashboard'), route('imported.in.create'), route('imported.out.create'), route('imported.transactions.index'),
            route('imported.products.index'), route('imported.products.create'), route('imported.products.edit', $imp), route('imported.stock.index'),
            route('imported.assemblies.index'), route('settings.index', 'suppliers'), route('settings.index', 'machines'), route('admin.users.index'),
            route('admin.roles.index'), route('admin.activity.index'), route('reports.show', 'imported-stock'), route('reports.export', ['report' => 'imported-in-daily', 'format' => 'csv']),
            route('adjustments.create', 'imported'), route('lookup.parts', 'imported'),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
        // write endpoints too — backend enforcement, not just hidden menus
        $this->post(route('imported.in.store'), ['spare_part_id' => $imp->id, 'quantity' => 5, 'transaction_date' => now()->toDateString()])->assertForbidden();
        $this->post(route('imported.products.store'), [])->assertForbidden();
        $this->assertSame(0.0, (float) $imp->fresh()->current_stock);

        // but CNC pages are fine
        foreach ([route('dashboard'), route('cnc.dashboard'), route('cnc.production.create'), route('cnc.stock.index'), route('reports.show', 'cnc-stock')] as $url) {
            $this->get($url)->assertOk();
        }
        // CNC part of the imported route prefix is 404, not visible
        $this->get(route('cnc.parts.show', $imp))->assertNotFound();
        // overall dashboard hides imported figures
        $this->get(route('dashboard'))->assertDontSee('Imported IN today');
    }

    /** Acceptance 10 */
    public function test_combined_user_can_access_both_operational_modules(): void
    {
        $this->actingAs($this->userWithRole(Role::COMBINED_USER));
        foreach ([route('cnc.dashboard'), route('cnc.production.create'), route('cnc.completions.create'), route('cnc.stock.index'), route('cnc.parts.create'),
            route('imported.dashboard'), route('imported.in.create'), route('imported.out.create'), route('imported.assemblies.create'), route('imported.products.create'),
            route('reports.show', 'cnc-production-daily'), route('reports.show', 'imported-out-daily'), route('dashboard')] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('dashboard'))->assertSee('Imported IN today')->assertSee('CNC production today');
        // but not administration
        $this->get(route('admin.users.index'))->assertForbidden();
        $part = $this->createCncPartViaHttp();
        $this->post(route('cnc.production.store'), $this->productionPayload($part, 3, 10))->assertSessionHasNoErrors();
        $this->post(route('cnc.completions.store'), ['completion_date' => now()->toDateString(), 'spare_part_id' => $part->id, 'quantity_accepted' => 10, 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->post(route('cnc.completions.approve', CncProductionCompletion::first()), ['quantity_accepted' => 10, 'quantity_rejected' => 0])->assertForbidden();
        $this->assertSame(0.0, (float) $part->fresh()->current_stock);
    }

    public function test_import_user_cannot_access_cnc(): void
    {
        $this->actingAs($this->userWithRole(Role::IMPORT_USER));
        foreach ([route('cnc.dashboard'), route('cnc.production.index'), route('cnc.production.create'), route('cnc.stock.index'), route('lookup.parts', 'cnc'), route('reports.show', 'cnc-stock')] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->get(route('imported.in.create'))->assertOk();
    }

    public function test_granular_extra_permission_and_deactivation(): void
    {
        $admin = $this->admin();
        $user = $this->userWithRole(Role::CNC_USER);
        $this->actingAs($user)->get(route('imported.stock.index'))->assertForbidden();

        $perm = Permission::where('name', 'imported.inventory.view')->first();
        $this->actingAs($admin)->put(route('admin.users.update', $user), ['name' => $user->name, 'username' => $user->username, 'role_id' => $user->role_id,
            'is_active' => 1, 'permission_ids' => [$perm->id]])->assertSessionHasNoErrors();
        $this->actingAs($user->fresh())->get(route('imported.stock.index'))->assertOk();

        $this->actingAs($admin)->post(route('admin.users.toggle', $user))->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->is_active);
        $this->actingAs($user->fresh())->get(route('cnc.dashboard'))->assertRedirect(route('login'));
    }

    public function test_role_permissions_editable_by_super_admin_only(): void
    {
        $role = Role::where('name', Role::CNC_USER)->first();
        $this->actingAs($this->userWithRole(Role::COMBINED_USER))->put(route('admin.roles.update', $role), ['display_name' => 'x'])->assertForbidden();
        $keep = $role->permissions()->where('name', '!=', 'cnc.production.create')->pluck('permissions.id')->all();
        $this->actingAs($this->admin())->put(route('admin.roles.update', $role), ['display_name' => 'CNC User', 'permission_ids' => $keep])->assertSessionHasNoErrors();
        $this->actingAs($this->userWithRole(Role::CNC_USER))->get(route('cnc.production.create'))->assertForbidden();
    }
}
