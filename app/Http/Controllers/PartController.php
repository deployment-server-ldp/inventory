<?php

namespace App\Http\Controllers;

use App\Models\CncInventoryTransaction;
use App\Models\ImportedInventoryTransaction;
use App\Models\MachineryModel;
use App\Models\Operation;
use App\Models\PartCategory;
use App\Models\SparePart;
use App\Models\SparePartImage;
use App\Models\Supplier;
use App\Models\Unit;
use App\Rules\QuantityForUnit;
use App\Services\ActivityLogger;
use App\Services\CncProductionService;
use App\Services\ImageService;
use App\Services\StockService;
use App\Support\Qty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Part master for both inventories. The route supplies {type}; a part of the other type is a 404. */
class PartController extends Controller
{
    public function __construct(private ImageService $images, private StockService $stock) {}

    public function index(Request $request): View
    {
        $type = $this->type();
        $query = SparePart::query()->type($type)->with(['category', 'unit', 'primaryImage', 'supplier'])->search($request->query('q'));
        if ($cat = $request->integer('category_id')) {
            $query->where('category_id', $cat);
        }
        match ($request->query('stock')) {
            'low' => $query->lowStock(),
            'out' => $query->outOfStock(),
            'ok' => $query->where('current_stock', '>', 0)->where(fn ($w) => $w->where('min_stock', '<=', 0)->orWhereColumn('current_stock', '>', 'min_stock')),
            default => null,
        };
        $status = $request->query('status', 'active');
        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('is_active', $status === 'active');
        }
        $this->applySort($query, $request, ['sku' => 'sku', 'name' => 'name', 'stock' => 'current_stock', 'min' => 'min_stock', 'updated' => 'updated_at'], 'name', 'asc');

        return view('parts.index', [
            'type' => $type,
            'parts' => $query->paginate($this->perPage($request))->withQueryString(),
            'categories' => PartCategory::forType($type)->orderBy('name')->get(),
            'canManage' => $request->user()->can($this->managePerm($type)),
        ]);
    }

    public function create(): View
    {
        $type = $this->type();

        return view('parts.form', $this->formData($type, new SparePart(['inventory_type' => $type, 'is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->type();
        $part = $this->persist($request, $type);

        return redirect()->route($part->routeBase().'.show', $part)->with('success', "{$part->sku} — {$part->name} created.");
    }

    /** AJAX creation from within IN / OUT / production forms, so the user keeps their form data. */
    public function quickStore(Request $request): JsonResponse
    {
        $type = $this->type();
        $part = $this->persist($request, $type);

        return response()->json(['data' => LookupController::present($part->load(['category', 'unit', 'primaryImage', 'supplier']))], 201);
    }

    public function show(Request $request, SparePart $part, CncProductionService $cnc): View
    {
        $type = $this->type();
        $this->assertType($part, $type);
        $part->load(['category', 'unit', 'supplier', 'finalOperation', 'images', 'machineryModels', 'creator']);
        $ledgerClass = $type === 'cnc' ? CncInventoryTransaction::class : ImportedInventoryTransaction::class;
        $recent = $ledgerClass::query()->where('spare_part_id', $part->id)->with('creator')->latest('id')->limit(15)->get();

        return view('parts.show', [
            'type' => $type,
            'part' => $part,
            'recent' => $recent,
            'awaiting' => $type === 'cnc' ? $cnc->awaitingCompletion($part->id) : null,
            'progress' => $type === 'cnc' ? $cnc->operationProgress($part->id) : [],
            'canManage' => $request->user()->can($this->managePerm($type)),
        ]);
    }

    public function edit(SparePart $part): View
    {
        $type = $this->type();
        $this->assertType($part, $type);

        return view('parts.form', $this->formData($type, $part->load(['machineryModels', 'images'])));
    }

    public function update(Request $request, SparePart $part): RedirectResponse
    {
        $type = $this->type();
        $this->assertType($part, $type);
        $part = $this->persist($request, $type, $part);

        return redirect()->route($part->routeBase().'.show', $part)->with('success', 'Part updated.');
    }

    public function addImage(Request $request, SparePart $part): RedirectResponse
    {
        $type = $this->type();
        $this->assertType($part, $type);
        $request->validate(['image' => ImageService::rules(true), 'primary' => ['nullable', 'boolean']]);
        $img = $this->images->store($part, $request->file('image'), $request->boolean('primary'));
        ActivityLogger::log('part.image_added', "Image uploaded for {$part->sku}".($img->is_primary ? ' (primary)' : ''), $part, [], ['image_id' => $img->id], $type);

        return back()->with('success', $img->is_primary ? 'Primary image replaced.' : 'Image added.');
    }

    public function primaryImage(SparePart $part, SparePartImage $image): RedirectResponse
    {
        $type = $this->type();
        $this->assertType($part, $type);
        abort_unless($image->spare_part_id === $part->id, 404);
        $this->images->makePrimary($image);
        ActivityLogger::log('part.image_primary', "Primary image changed for {$part->sku}", $part, [], ['image_id' => $image->id], $type);

        return back()->with('success', 'Primary image updated.');
    }

    public function deleteImage(SparePart $part, SparePartImage $image): RedirectResponse
    {
        $type = $this->type();
        $this->assertType($part, $type);
        abort_unless($image->spare_part_id === $part->id, 404);
        $this->images->delete($image);
        ActivityLogger::log('part.image_removed', "Additional image removed from {$part->sku}", $part, ['image_id' => $image->id], [], $type);

        return back()->with('success', 'Image removed.');
    }

    // ------------------------------------------------------------------

    private function persist(Request $request, string $type, ?SparePart $part = null): SparePart
    {
        $isNew = $part === null;
        $rules = [
            'sku' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-_.\/]*$/', Rule::unique('spare_parts', 'sku')->ignore($part?->id)],
            'name' => ['required', 'string', 'max:191'],
            'category_id' => ['required', Rule::exists('part_categories', 'id')->whereIn('scope', [$type, 'both'])],
            'unit_id' => ['required', Rule::exists('units', 'id')],
            'specification' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999', new QuantityForUnit((int) $request->input('unit_id'))],
            'machinery_model_ids' => ['nullable', 'array'],
            'machinery_model_ids.*' => ['integer', Rule::exists('machinery_models', 'id')],
            'is_active' => ['boolean'],
            'primary_image' => ImageService::rules($isNew),
            'additional_images' => ['nullable', 'array', 'max:8'],
            'additional_images.*' => ImageService::rules(false),
        ];
        if ($isNew) {
            $rules['opening_stock'] = ['nullable', 'numeric', 'min:0', 'max:999999999', new QuantityForUnit((int) $request->input('unit_id'))];
        }
        if ($type === 'cnc') {
            $rules['final_operation_id'] = ['required', Rule::exists('operations', 'id')];
        } else {
            $rules += [
                'brand' => ['nullable', 'string', 'max:100'],
                'part_number' => ['nullable', 'string', 'max:100'],
                'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')],
                'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
                'currency' => ['nullable', 'required_with:unit_cost', Rule::in(config('spims.currencies'))],
            ];
        }
        $request->merge(['is_active' => $request->boolean('is_active', $isNew)]);
        $data = $request->validate($rules, [
            'primary_image.required' => 'A primary image is mandatory for every spare part.',
            'sku.regex' => 'SKU may contain letters, numbers, dash, underscore, dot and slash only.',
        ], ['category_id' => 'category', 'unit_id' => 'unit', 'final_operation_id' => 'final operation']);

        return DB::transaction(function () use ($request, $data, $type, $part, $isNew) {
            $part ??= new SparePart(['inventory_type' => $type, 'created_by' => $request->user()->id]);
            $original = $part->getAttributes();
            $fields = ['sku', 'name', 'category_id', 'unit_id', 'specification', 'description', 'min_stock', 'is_active', 'final_operation_id', 'brand', 'part_number', 'supplier_id', 'unit_cost', 'currency'];
            $part->fill(array_intersect_key($data, array_flip($fields)));
            $part->min_stock = $data['min_stock'] ?? 0;
            $part->inventory_type = $type;
            $part->updated_by = $request->user()->id;
            if ($isNew) {
                $part->opening_stock = Qty::round($data['opening_stock'] ?? 0);
                $part->current_stock = 0;
            }
            $part->save();
            $part->machineryModels()->sync($data['machinery_model_ids'] ?? []);

            if ($request->hasFile('primary_image')) {
                $this->images->store($part, $request->file('primary_image'), true);
            }
            foreach ($request->file('additional_images', []) as $file) {
                $this->images->store($part, $file, false);
            }

            if ($isNew) {
                if ((float) $part->opening_stock > 0) {
                    $this->stock->post($type, $part->id, 'opening', (float) $part->opening_stock, ['remarks' => 'Opening stock at part registration']);
                }
                ActivityLogger::log('part.created', "Created {$type} part {$part->sku} — {$part->name}".((float) $part->opening_stock > 0 ? ' with opening stock '.Qty::fmt($part->opening_stock) : ''), $part, [], $part->only(['sku', 'name', 'category_id', 'unit_id', 'min_stock', 'opening_stock']), $type);
            } else {
                ActivityLogger::logChanges('part.updated', "Updated {$type} part {$part->sku}", $part, $original, $type);
            }

            return $part->refresh();
        });
    }

    private function formData(string $type, SparePart $part): array
    {
        return [
            'type' => $type,
            'part' => $part,
            'categories' => PartCategory::forType($type)->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $part->category_id))->orderBy('name')->get(),
            'units' => Unit::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $part->unit_id))->orderBy('name')->get(),
            'operations' => Operation::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $part->final_operation_id))->ordered()->get(),
            'suppliers' => Supplier::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $part->supplier_id))->orderBy('name')->get(),
            'models' => MachineryModel::active()->orderBy('name')->get(),
            'defaultFinalOp' => Operation::active()->orderByDesc('sequence')->value('id'),
        ];
    }

    /** Inventory type from the route name: cnc.parts.* or imported.products.* */
    private function type(): string
    {
        return str_starts_with((string) request()->route()?->getName(), 'cnc.') ? 'cnc' : 'imported';
    }

    private function assertType(SparePart $part, string $type): void
    {
        abort_unless($part->inventory_type === $type, 404);
    }

    private function managePerm(string $type): string
    {
        return $type === 'cnc' ? 'cnc.parts.manage' : 'imported.products.manage';
    }
}
