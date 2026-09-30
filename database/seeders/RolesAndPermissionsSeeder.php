<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/** Idempotent: safe to re-run after upgrades to add new permissions. Existing role edits are preserved. */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissions::ALL as $group => $perms) {
            foreach ($perms as $name => $label) {
                Permission::updateOrCreate(['name' => $name], ['display_name' => $label, 'group' => $group]);
            }
        }

        foreach (Permissions::roleDefaults() as $name => $def) {
            $role = Role::firstOrNew(['name' => $name]);
            $isNew = ! $role->exists;
            $role->fill(['display_name' => $def['display'], 'description' => $def['description'], 'is_system' => true])->save();
            $ids = Permission::whereIn('name', $def['permissions'])->pluck('id');
            if ($isNew || $name === Role::SUPER_ADMIN) {
                $role->permissions()->syncWithoutDetaching($ids);
            }
        }
    }
}
