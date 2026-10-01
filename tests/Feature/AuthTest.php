<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\PartCategory;
use App\Models\Role;
use App\Models\SparePart;
use App\Models\Unit;
use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_guest_is_redirected_to_setup_when_no_users_then_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertRedirect(route('setup'));
        $this->get(route('setup'))->assertOk();
    }

    public function test_first_admin_setup_requires_token_and_works_once(): void
    {
        $data = ['name' => 'Owner', 'username' => 'owner', 'password' => 'Secret1234Ab', 'password_confirmation' => 'Secret1234Ab'];
        $this->post(route('setup'), $data + ['setup_token' => 'wrong'])->assertSessionHasErrors('setup_token');
        $this->assertSame(0, User::count());

        $this->post(route('setup'), $data + ['setup_token' => 'test-setup-token-123'])->assertRedirect(route('dashboard'));
        $user = User::first();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertNotSame('Secret1234Ab', $user->password, 'password is hashed');

        auth()->logout();
        $this->get(route('setup'))->assertNotFound();
        $this->post(route('setup'), $data + ['setup_token' => 'test-setup-token-123', 'username' => 'attacker'])->assertNotFound();
    }

    public function test_login_logout_logged_and_inactive_users_blocked(): void
    {
        $user = User::factory()->create(['username' => 'asif', 'password' => 'Password#123', 'role_id' => Role::where('name', Role::CNC_USER)->value('id')]);
        $this->post('/login', ['username' => 'asif', 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post('/login', ['username' => 'asif', 'password' => 'Password#123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertTrue(ActivityLog::where('action', 'auth.login')->where('user_id', $user->id)->exists());
        $this->assertTrue(ActivityLog::where('action', 'auth.logout')->where('user_id', $user->id)->exists());
        $this->assertTrue(ActivityLog::where('action', 'auth.failed')->exists());

        $user->update(['is_active' => false]);
        $this->post('/login', ['username' => 'asif', 'password' => 'Password#123'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_admin_password_reset_forces_change(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', Role::SUPER_ADMIN)->value('id')]);
        $user = User::factory()->create(['role_id' => Role::where('name', Role::IMPORT_USER)->value('id')]);
        $this->actingAs($admin)->post(route('admin.users.reset-password', $user), ['password' => 'Temp12345'])->assertSessionHas('success');
        $this->assertTrue($user->fresh()->must_change_password);
        $this->actingAs($user->fresh())->get(route('imported.dashboard'))->assertRedirect(route('password.change'));
        $this->put(route('password.change.update'), ['current_password' => 'Temp12345', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])->assertRedirect(route('dashboard'));
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_security_headers_and_health(): void
    {
        $this->get(route('login'))->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson(route('health'))->assertOk()->assertJson(['status' => 'ok'])->assertJsonMissing(['checks']);
    }

    public function test_media_requires_authentication(): void
    {
        $this->get('/media/part-image/1/thumb')->assertRedirect(route('login'));
    }

    public function test_missing_image_file_shows_placeholder(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('name', Role::SUPER_ADMIN)->value('id')]));
        $part = SparePart::create(['inventory_type' => 'imported', 'sku' => 'PH-1', 'name' => 'Placeholder', 'category_id' => PartCategory::value('id'), 'unit_id' => Unit::value('id')]);
        $img = $part->images()->create(['path' => 'parts/none.png', 'thumb_path' => 'parts/none_t.png', 'mime' => 'image/png', 'size' => 1, 'is_primary' => true]);
        $this->get(route('media.part-image', [$img->id, 'thumb']))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }
}
