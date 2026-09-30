<?php

namespace App\Support;

use App\Models\Role;

/**
 * Canonical permission catalogue and the default permission set of the four initial roles.
 * The Super Admin can change role permissions at runtime (Admin → Roles); this list is used by
 * the seeder only to create missing permissions and initial role assignments.
 */
class Permissions
{
    public const ALL = [
        'Dashboard' => [
            'dashboard.overall' => 'View overall dashboard',
        ],
        'CNC Production' => [
            'cnc.dashboard' => 'View CNC production dashboard',
            'cnc.production.view' => 'View production entries & machine history',
            'cnc.production.create' => 'Create / finish production entries',
            'cnc.production.edit' => 'Edit production entries',
            'cnc.production.cancel' => 'Cancel production entries',
            'cnc.completion.create' => 'Submit production completions',
            'cnc.completion.approve' => 'Approve / reject / reverse completions (quality)',
        ],
        'CNC Inventory' => [
            'cnc.inventory.view' => 'View CNC stock & ledger',
            'cnc.inventory.issue' => 'Issue CNC finished stock',
            'cnc.inventory.reverse' => 'Reverse CNC stock issues',
            'cnc.parts.manage' => 'Create / edit CNC part master & images',
            'cnc.stock.adjust' => 'CNC stock adjustments',
        ],
        'Imported Inventory' => [
            'imported.dashboard' => 'View imported inventory dashboard',
            'imported.inventory.view' => 'View imported stock, transactions & ledger',
            'imported.in.create' => 'Record Inventory IN',
            'imported.out.create' => 'Record Inventory OUT',
            'imported.transactions.reverse' => 'Reverse IN / OUT transactions',
            'imported.products.manage' => 'Create / edit imported product master & images',
            'imported.stock.adjust' => 'Imported stock adjustments',
            'imported.assembly.view' => 'View machine assemblies',
            'imported.assembly.manage' => 'Create / manage assemblies & issue parts',
            'imported.purpose.manual' => 'Enter a free-text machine / purpose on OUT',
        ],
        'Reports' => [
            'reports.cnc' => 'CNC reports & exports',
            'reports.imported' => 'Imported inventory reports & exports',
        ],
        'Settings' => [
            'settings.machines' => 'Manage CNC machines',
            'settings.operators' => 'Manage machine operators',
            'settings.masters' => 'Manage categories, units, operations & machinery models',
            'settings.suppliers' => 'Manage suppliers',
            'settings.company' => 'Manage company settings',
        ],
        'Administration' => [
            'users.manage' => 'Manage users & reset passwords',
            'roles.manage' => 'Manage roles & permissions',
            'audit.view' => 'View activity log',
            'system.health' => 'View system health & error log',
        ],
    ];

    public static function flat(): array
    {
        return array_merge(...array_values(self::ALL));
    }

    public static function roleDefaults(): array
    {
        $cnc = [
            'dashboard.overall', 'cnc.dashboard', 'cnc.production.view', 'cnc.production.create', 'cnc.production.edit',
            'cnc.production.cancel', 'cnc.completion.create', 'cnc.inventory.view', 'cnc.inventory.issue', 'cnc.parts.manage',
            'reports.cnc',
        ];
        $imported = [
            'dashboard.overall', 'imported.dashboard', 'imported.inventory.view', 'imported.in.create', 'imported.out.create',
            'imported.products.manage', 'imported.assembly.view', 'imported.assembly.manage', 'reports.imported',
        ];

        return [
            Role::SUPER_ADMIN => ['display' => 'Super Admin', 'description' => 'Full access to all modules, settings, users and audit logs.', 'permissions' => array_keys(self::flat())],
            Role::CNC_USER => ['display' => 'CNC User', 'description' => 'CNC production entries, CNC dashboards and CNC inventory.', 'permissions' => $cnc],
            Role::IMPORT_USER => ['display' => 'Import Inventory User', 'description' => 'Imported inventory IN / OUT, assemblies, imported dashboards and reports.', 'permissions' => $imported],
            Role::COMBINED_USER => ['display' => 'Combined User', 'description' => 'Both CNC and imported inventory operations.', 'permissions' => array_values(array_unique(array_merge($cnc, $imported)))],
        ];
    }
}
