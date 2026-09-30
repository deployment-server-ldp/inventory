<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\MachineryModel;
use App\Models\Operation;
use App\Models\Operator;
use App\Models\PartCategory;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Validation\Rule;

/**
 * Declarative definition of every master-data screen (Settings). One generic controller and one
 * set of views render all of them. Records can be added, edited, searched and deactivated —
 * never deleted, so historical transactions always keep their references.
 */
class MasterDataRegistry
{
    public static function all(): array
    {
        return [
            'machines' => [
                'title' => 'CNC Machines', 'singular' => 'machine', 'model' => Machine::class, 'permission' => 'settings.machines',
                'icon' => 'bi-cpu', 'search' => ['code', 'name', 'description'], 'order' => ['sort_order', 'asc'],
                'toggle' => 'status',
                'help' => 'CNC machines available in production entry. Machines under maintenance or inactive cannot receive new production entries.',
                'fields' => [
                    'code' => ['label' => 'Machine code', 'type' => 'text', 'rules' => fn ($id) => ['required', 'string', 'max:30', Rule::unique('machines', 'code')->ignore($id)], 'list' => true],
                    'name' => ['label' => 'Machine name', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'list' => true],
                    'status' => ['label' => 'Status', 'type' => 'select', 'options' => Machine::STATUSES, 'rules' => ['required', Rule::in(array_keys(Machine::STATUSES))], 'list' => true, 'default' => 'active'],
                    'sort_order' => ['label' => 'Display order', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0', 'max:9999'], 'list' => false],
                    'description' => ['label' => 'Description', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:1000'], 'list' => true],
                ],
            ],
            'operators' => [
                'title' => 'Machine Operators', 'singular' => 'operator', 'model' => Operator::class, 'permission' => 'settings.operators',
                'icon' => 'bi-person-badge', 'search' => ['employee_code', 'name', 'contact'], 'order' => ['name', 'asc'],
                'fields' => [
                    'employee_code' => ['label' => 'Employee code', 'type' => 'text', 'rules' => fn ($id) => ['required', 'string', 'max:30', Rule::unique('operators', 'employee_code')->ignore($id)], 'list' => true],
                    'name' => ['label' => 'Name', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'list' => true],
                    'contact' => ['label' => 'Contact (optional)', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:50'], 'list' => true],
                ],
            ],
            'operations' => [
                'title' => 'Operations', 'singular' => 'operation', 'model' => Operation::class, 'permission' => 'settings.masters',
                'icon' => 'bi-123', 'search' => ['name', 'description'], 'order' => ['sequence', 'asc'],
                'help' => 'Machining operations in their sequence. Each CNC part defines which operation is its final one; only final-operation output can be completed into stock.',
                'fields' => [
                    'name' => ['label' => 'Operation name', 'type' => 'text', 'rules' => fn ($id) => ['required', 'string', 'max:50', Rule::unique('operations', 'name')->ignore($id)], 'list' => true],
                    'sequence' => ['label' => 'Sequence', 'type' => 'number', 'rules' => fn ($id) => ['required', 'integer', 'min:1', 'max:99', Rule::unique('operations', 'sequence')->ignore($id)], 'list' => true],
                    'description' => ['label' => 'Description', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255'], 'list' => true],
                ],
            ],
            'categories' => [
                'title' => 'Part Categories', 'singular' => 'category', 'model' => PartCategory::class, 'permission' => 'settings.masters',
                'icon' => 'bi-folder2', 'search' => ['name', 'description'], 'order' => ['name', 'asc'],
                'fields' => [
                    'name' => ['label' => 'Category name', 'type' => 'text', 'rules' => fn ($id) => ['required', 'string', 'max:100', Rule::unique('part_categories', 'name')->where('scope', request('scope'))->ignore($id)], 'list' => true],
                    'scope' => ['label' => 'Used for', 'type' => 'select', 'options' => PartCategory::SCOPES, 'rules' => ['required', Rule::in(array_keys(PartCategory::SCOPES))], 'list' => true, 'default' => 'both'],
                    'description' => ['label' => 'Description', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255'], 'list' => true],
                ],
            ],
            'units' => [
                'title' => 'Units of Measurement', 'singular' => 'unit', 'model' => Unit::class, 'permission' => 'settings.masters',
                'icon' => 'bi-rulers', 'search' => ['name', 'symbol'], 'order' => ['name', 'asc'],
                'fields' => [
                    'name' => ['label' => 'Unit name', 'type' => 'text', 'rules' => fn ($id) => ['required', 'string', 'max:50', Rule::unique('units', 'name')->ignore($id)], 'list' => true],
                    'symbol' => ['label' => 'Symbol', 'type' => 'text', 'rules' => fn ($id) => ['required', 'string', 'max:20', Rule::unique('units', 'symbol')->ignore($id)], 'list' => true],
                    'allows_decimal' => ['label' => 'Allow decimal quantities (e.g. metres, kg)', 'type' => 'checkbox', 'rules' => ['boolean'], 'list' => true],
                ],
            ],
            'machinery-models' => [
                'title' => 'Machinery Models', 'singular' => 'machinery model', 'model' => MachineryModel::class, 'permission' => 'settings.masters',
                'icon' => 'bi-building-gear', 'search' => ['name', 'manufacturer', 'model_code'], 'order' => ['name', 'asc'],
                'help' => 'Cigarette manufacturing machinery models (target machines for spare parts and consumption).',
                'fields' => [
                    'name' => ['label' => 'Model name', 'type' => 'text', 'rules' => fn ($id) => ['required', 'string', 'max:100', Rule::unique('machinery_models', 'name')->ignore($id)], 'list' => true],
                    'manufacturer' => ['label' => 'Manufacturer', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100'], 'list' => true],
                    'model_code' => ['label' => 'Model code', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:50'], 'list' => true],
                    'description' => ['label' => 'Description', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255'], 'list' => false],
                ],
            ],
            'suppliers' => [
                'title' => 'Suppliers', 'singular' => 'supplier', 'model' => Supplier::class, 'permission' => 'settings.suppliers',
                'icon' => 'bi-truck', 'search' => ['name', 'country', 'contact_person', 'phone', 'email'], 'order' => ['name', 'asc'],
                'fields' => [
                    'name' => ['label' => 'Supplier name', 'type' => 'text', 'rules' => fn ($id) => ['required', 'string', 'max:150', Rule::unique('suppliers', 'name')->ignore($id)], 'list' => true],
                    'country' => ['label' => 'Country', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:80'], 'list' => true],
                    'contact_person' => ['label' => 'Contact person', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100'], 'list' => true],
                    'phone' => ['label' => 'Phone', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:50'], 'list' => true],
                    'email' => ['label' => 'E-mail', 'type' => 'email', 'rules' => ['nullable', 'email', 'max:150'], 'list' => false],
                    'address' => ['label' => 'Address', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255'], 'list' => false],
                ],
            ],
        ];
    }

    public static function get(string $entity): array
    {
        $all = self::all();
        abort_unless(isset($all[$entity]), 404);

        return $all[$entity] + ['key' => $entity, 'toggle' => $all[$entity]['toggle'] ?? 'is_active'];
    }
}
