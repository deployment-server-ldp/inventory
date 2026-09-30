<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\MasterDataRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request, string $entity): View
    {
        $def = $this->authorizeEntity($entity);
        $query = $def['model']::query();
        if ($q = $request->query('q')) {
            $query->where(function ($w) use ($def, $q) {
                foreach ($def['search'] as $col) {
                    $w->orWhere($col, 'like', self::like($q));
                }
            });
        }
        if (in_array($request->query('status'), ['active', 'inactive'], true)) {
            $def['toggle'] === 'status'
                ? $query->where('status', $request->query('status') === 'active' ? '=' : '!=', 'active')
                : $query->where('is_active', $request->query('status') === 'active');
        }
        $sortable = array_combine(array_keys($def['fields']), array_keys($def['fields']));
        $this->applySort($query, $request, $sortable, $def['order'][0], $def['order'][1]);

        return view('settings.index', ['def' => $def, 'rows' => $query->paginate($this->perPage($request))->withQueryString()]);
    }

    public function create(string $entity): View
    {
        $def = $this->authorizeEntity($entity);

        return view('settings.form', ['def' => $def, 'row' => new $def['model']]);
    }

    public function store(Request $request, string $entity): RedirectResponse
    {
        $def = $this->authorizeEntity($entity);
        $data = $this->validated($request, $def, null);
        /** @var Model $row */
        $row = $def['model']::create($data);
        ActivityLogger::log('master.created', "Created {$def['singular']} '".$this->label($row)."'", $row, [], $data, 'settings');

        return redirect()->route('settings.index', $entity)->with('success', ucfirst($def['singular']).' created.');
    }

    public function edit(string $entity, int $id): View
    {
        $def = $this->authorizeEntity($entity);

        return view('settings.form', ['def' => $def, 'row' => $def['model']::findOrFail($id)]);
    }

    public function update(Request $request, string $entity, int $id): RedirectResponse
    {
        $def = $this->authorizeEntity($entity);
        $row = $def['model']::findOrFail($id);
        $data = $this->validated($request, $def, $id);
        $original = $row->getAttributes();
        $row->fill($data)->save();
        ActivityLogger::logChanges('master.updated', "Updated {$def['singular']} '".$this->label($row)."'", $row, $original, 'settings');

        return redirect()->route('settings.index', $entity)->with('success', ucfirst($def['singular']).' updated.');
    }

    /** Activate / deactivate. There is intentionally no delete. */
    public function toggle(string $entity, int $id): RedirectResponse
    {
        $def = $this->authorizeEntity($entity);
        $row = $def['model']::findOrFail($id);
        $original = $row->getAttributes();
        if ($def['toggle'] === 'status') {
            $row->status = $row->status === 'active' ? 'inactive' : 'active';
            $active = $row->status === 'active';
        } else {
            $row->is_active = ! $row->is_active;
            $active = $row->is_active;
        }
        $row->save();
        ActivityLogger::logChanges($active ? 'master.activated' : 'master.deactivated', ($active ? 'Activated' : 'Deactivated')." {$def['singular']} '".$this->label($row)."'", $row, $original, 'settings');

        return back()->with('success', ucfirst($def['singular']).($active ? ' activated.' : ' deactivated.'));
    }

    private function authorizeEntity(string $entity): array
    {
        $def = MasterDataRegistry::get($entity);
        abort_unless(request()->user()->hasPermission($def['permission']), 403);

        return $def;
    }

    private function validated(Request $request, array $def, ?int $id): array
    {
        $rules = [];
        foreach ($def['fields'] as $name => $f) {
            $rules[$name] = is_callable($f['rules']) ? ($f['rules'])($id) : $f['rules'];
            if ($f['type'] === 'checkbox') {
                $request->merge([$name => $request->boolean($name)]);
            }
        }
        if ($def['toggle'] === 'is_active') {
            $request->merge(['is_active' => $request->boolean('is_active')]);
            $rules['is_active'] = ['boolean'];
        }
        $data = $request->validate($rules, [], array_map(fn ($f) => $f['label'], $def['fields']));
        if (array_key_exists('sort_order', $data)) {
            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        }

        return $data;
    }

    private function label(Model $row): string
    {
        return (string) ($row->name ?? $row->code ?? $row->getKey());
    }
}
