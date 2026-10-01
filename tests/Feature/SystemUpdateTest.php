<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SystemController;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\SparePart;
use Tests\Feature\Concerns\CreatesParts;
use Tests\TestCase;

class SystemUpdateTest extends TestCase
{
    use CreatesParts;

    public function test_health_page_shows_version_and_no_pending_migrations(): void
    {
        $this->assertSame([], SystemController::pendingMigrations());
        $this->actingAs($this->admin())->get(route('admin.system.health'))->assertOk()
            ->assertSee('Database up to date')->assertSee('Version '.trim(file_get_contents(base_path('VERSION'))));
    }

    public function test_apply_updates_keeps_existing_data(): void
    {
        $this->actingAs($this->admin());
        $part = $this->createImportedPartViaHttp();
        $this->post(route('admin.system.apply-updates'))->assertSessionHas('success');
        $this->assertTrue(SparePart::whereKey($part->id)->exists());
        $this->assertTrue(ActivityLog::where('action', 'system.updated')->exists());
    }

    public function test_only_super_admin_can_apply_updates(): void
    {
        $this->actingAs($this->userWithRole(Role::COMBINED_USER))->post(route('admin.system.apply-updates'))->assertForbidden();
    }
}
