<?php

namespace App\Support;

use App\Models\User;

/** Sidebar definition. Items are only shown when the user has the permission; routes enforce it too. */
class Navigation
{
    public static function items(User $user): array
    {
        $sections = [
            ['label' => null, 'items' => [
                ['Dashboard', 'dashboard', 'bi-speedometer2', 'dashboard.overall', 'dashboard'],
            ]],
            ['label' => 'CNC Manufacturing', 'items' => [
                ['CNC Dashboard', 'cnc.dashboard', 'bi-gear-wide-connected', 'cnc.dashboard', 'cnc.dashboard'],
                ['New Production Entry', 'cnc.production.create', 'bi-plus-square', 'cnc.production.create', 'cnc.production.create'],
                ['Production Entries', 'cnc.production.index', 'bi-list-check', 'cnc.production.view', 'cnc.production.index|cnc.production.show|cnc.production.edit'],
                ['Operation Progress', 'cnc.progress', 'bi-diagram-3', 'cnc.production.view', 'cnc.progress'],
                ['CNC Machines', 'cnc.machines.index', 'bi-cpu', 'cnc.production.view', 'cnc.machines.*'],
                ['Completions / QC', 'cnc.completions.index', 'bi-patch-check', ['cnc.completion.create', 'cnc.completion.approve'], 'cnc.completions.*'],
                ['CNC Stock', 'cnc.stock.index', 'bi-boxes', 'cnc.inventory.view', 'cnc.stock.*'],
                ['CNC Parts Master', 'cnc.parts.index', 'bi-nut', ['cnc.inventory.view', 'cnc.parts.manage'], 'cnc.parts.*'],
            ]],
            ['label' => 'Imported Inventory', 'items' => [
                ['Imported Dashboard', 'imported.dashboard', 'bi-globe2', 'imported.dashboard', 'imported.dashboard'],
                ['Inventory IN', 'imported.in.create', 'bi-box-arrow-in-down', 'imported.in.create', 'imported.in.*'],
                ['Inventory OUT', 'imported.out.create', 'bi-box-arrow-up', 'imported.out.create', 'imported.out.*'],
                ['Transactions', 'imported.transactions.index', 'bi-arrow-left-right', 'imported.inventory.view', 'imported.transactions.*'],
                ['Imported Stock', 'imported.stock.index', 'bi-box-seam', 'imported.inventory.view', 'imported.stock.*'],
                ['Machine Assemblies', 'imported.assemblies.index', 'bi-tools', 'imported.assembly.view', 'imported.assemblies.*'],
                ['Products Master', 'imported.products.index', 'bi-tags', ['imported.inventory.view', 'imported.products.manage'], 'imported.products.*'],
            ]],
            ['label' => 'Analysis', 'items' => [
                ['Stock Adjustments', 'adjustments.index', 'bi-sliders', ['cnc.stock.adjust', 'imported.stock.adjust'], 'adjustments.*'],
                ['Reports Center', 'reports.index', 'bi-file-earmark-bar-graph', ['reports.cnc', 'reports.imported'], 'reports.*'],
            ]],
            ['label' => 'Settings', 'items' => [
                ['CNC Machines', 'settings.index', 'bi-cpu', 'settings.machines', 'settings.*:machines', ['entity' => 'machines']],
                ['Operators', 'settings.index', 'bi-person-badge', 'settings.operators', 'settings.*:operators', ['entity' => 'operators']],
                ['Operations', 'settings.index', 'bi-123', 'settings.masters', 'settings.*:operations', ['entity' => 'operations']],
                ['Part Categories', 'settings.index', 'bi-folder2', 'settings.masters', 'settings.*:categories', ['entity' => 'categories']],
                ['Units', 'settings.index', 'bi-rulers', 'settings.masters', 'settings.*:units', ['entity' => 'units']],
                ['Machinery Models', 'settings.index', 'bi-building-gear', 'settings.masters', 'settings.*:machinery-models', ['entity' => 'machinery-models']],
                ['Suppliers', 'settings.index', 'bi-truck', 'settings.suppliers', 'settings.*:suppliers', ['entity' => 'suppliers']],
                ['Company', 'admin.company.edit', 'bi-building', 'settings.company', 'admin.company.*'],
            ]],
            ['label' => 'Administration', 'items' => [
                ['Users', 'admin.users.index', 'bi-people', 'users.manage', 'admin.users.*'],
                ['Roles & Permissions', 'admin.roles.index', 'bi-shield-lock', 'roles.manage', 'admin.roles.*'],
                ['Activity Log', 'admin.activity.index', 'bi-journal-text', 'audit.view', 'admin.activity.*'],
                ['System Health', 'admin.system.health', 'bi-heart-pulse', 'system.health', 'admin.system.*'],
            ]],
        ];

        $out = [];
        foreach ($sections as $section) {
            $items = [];
            foreach ($section['items'] as $item) {
                [$label, $route, $icon, $perm, $active] = $item;
                $params = $item[5] ?? [];
                if (! $user->hasAnyPermission((array) $perm)) {
                    continue;
                }
                $items[] = [
                    'label' => $label, 'url' => route($route, $params), 'icon' => $icon,
                    'active' => self::isActive($active),
                ];
            }
            if ($items) {
                $out[] = ['label' => $section['label'], 'items' => $items];
            }
        }

        return $out;
    }

    private static function isActive(string $pattern): bool
    {
        $entity = null;
        if (str_contains($pattern, ':')) {
            [$pattern, $entity] = explode(':', $pattern, 2);
        }
        if (! request()->routeIs(...explode('|', $pattern))) {
            return false;
        }

        return $entity === null || request()->route('entity') === $entity;
    }
}
