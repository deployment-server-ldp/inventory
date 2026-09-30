<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', ['roles' => Role::withCount(['users', 'permissions'])->orderBy('id')->get()]);
    }

    public function create(): View
    {
        return view('admin.roles.form', ['role' => new Role, 'groups' => Permission::orderBy('name')->get()->groupBy('group'), 'selected' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $role = Role::create(['name' => Str::slug($data['display_name'], '_'), 'display_name' => $data['display_name'], 'description' => $data['description'] ?? null]);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        ActivityLogger::log('role.created', "Created role {$role->display_name}", $role, [], ['permissions' => $role->permissions()->pluck('name')->all()], 'admin');

        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.form', ['role' => $role, 'groups' => Permission::orderBy('name')->get()->groupBy('group'), 'selected' => $role->permissions()->pluck('permissions.id')->all()]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validated($request, $role);
        $old = $role->permissions()->pluck('name')->all();
        $role->update(['display_name' => $data['display_name'], 'description' => $data['description'] ?? null]);
        if ($role->name !== Role::SUPER_ADMIN) { // Super Admin always has everything
            $role->permissions()->sync($data['permission_ids'] ?? []);
        }
        $new = $role->permissions()->pluck('name')->all();
        ActivityLogger::log('role.updated', "Updated role {$role->display_name}", $role, ['permissions' => $old], ['permissions' => $new], 'admin');

        return redirect()->route('admin.roles.index')->with('success', 'Role updated.');
    }

    private function validated(Request $request, ?Role $role): array
    {
        return $request->validate([
            'display_name' => ['required', 'string', 'max:100', Rule::unique('roles', 'display_name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);
    }
}
