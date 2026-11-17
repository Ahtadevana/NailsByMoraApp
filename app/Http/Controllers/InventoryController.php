<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['items', 'categories'])],
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'low_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name_asc', 'name_desc', 'quantity_asc', 'quantity_desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ]);

        $query = Inventory::query()->with('category:id,name');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $items) use ($search): void {
                $items->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhereHas('category', fn (Builder $categories) => $categories->where('name', 'like', '%'.$search.'%'));
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['low_stock'])) {
            $query->whereColumn('quantity', '<', 'minimum_stock');
        }

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('created_at'),
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'quantity_asc' => $query->orderBy('quantity')->orderBy('name'),
            'quantity_desc' => $query->orderByDesc('quantity')->orderBy('name'),
            default => $query->orderByDesc('created_at'),
        };

        $perPage = (int) ($filters['per_page'] ?? 10);
        $items = $query->paginate($perPage)->withQueryString()->through(
            fn (Inventory $item): array => $this->itemRow($item),
        );

        $stats = Inventory::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN quantity < minimum_stock THEN 1 ELSE 0 END) as low_stock')
            ->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as stock_value')
            ->first();

        return Inertia::render('Inventory/Index', [
            'items' => $items,
            'categories' => InventoryCategory::query()
                ->orderBy('name')
                ->get(['id', 'name', 'description'])
                ->loadCount('items'),
            'filters' => [
                'tab' => $filters['tab'] ?? 'items',
                'search' => $filters['search'] ?? '',
                'category_id' => $filters['category_id'] ?? '',
                'low_stock' => (bool) ($filters['low_stock'] ?? false),
                'sort' => $filters['sort'] ?? 'newest',
                'per_page' => $perPage,
            ],
            'summary' => [
                'total' => (int) $stats->total,
                'low_stock' => (int) $stats->low_stock,
                'categories' => InventoryCategory::query()->count(),
                'stock_value' => (float) $stats->stock_value,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->itemRules());
        $item = new Inventory($validated);
        $item->quantity = '0.00';
        $item->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $item->name.' ditambahkan dengan stok nol. Gunakan Atur Stok untuk mencatat stok masuk.',
        ]);
    }

    public function update(Request $request, Inventory $inventoryItem): RedirectResponse
    {
        $inventoryItem->update($request->validate($this->itemRules()));

        return back()->with('toast', ['type' => 'success', 'message' => $inventoryItem->name.' berhasil diperbarui.']);
    }

    public function show(Inventory $inventoryItem): JsonResponse
    {
        $inventoryItem->load('category:id,name');

        return response()->json(['item' => $this->itemRow($inventoryItem)]);
    }

    public function history(Inventory $inventoryItem): JsonResponse
    {
        $inventoryItem->load('category:id,name');
        $currentBalance = $this->quantityToMinorUnits($inventoryItem->quantity);
        $movements = $inventoryItem->movements()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
        $history = [];

        foreach ($movements as $movement) {
            $after = $currentBalance;
            $quantity = $this->quantityToMinorUnits($movement->quantity);
            $currentBalance += $movement->type === 'IN' ? -$quantity : $quantity;
            $history[] = [
                'id' => $movement->id,
                'type' => $movement->type,
                'quantity' => $movement->quantity,
                'reason' => $movement->reason,
                'note' => $movement->note,
                'created_at' => $movement->created_at,
                'resulting_balance' => $this->minorUnitsToQuantity($after),
            ];
        }

        return response()->json([
            'item' => $this->itemRow($inventoryItem),
            'movements' => $history,
        ]);
    }

    public function adjustStock(Request $request, Inventory $inventoryItem): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['IN', 'OUT'])],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'reason' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:10000'],
        ]);
        $requestedQuantity = $this->quantityToMinorUnits((string) $validated['quantity']);

        DB::transaction(function () use ($inventoryItem, $validated, $requestedQuantity): void {
            $lockedItem = Inventory::query()->whereKey($inventoryItem->id)->lockForUpdate()->firstOrFail();
            $currentQuantity = $this->quantityToMinorUnits($lockedItem->quantity);

            if ($validated['type'] === 'OUT' && $requestedQuantity > $currentQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok tidak mencukupi. Stok saat ini '.$lockedItem->quantity.' '.$lockedItem->unit.'.',
                ]);
            }

            $resultingQuantity = $validated['type'] === 'IN'
                ? $currentQuantity + $requestedQuantity
                : $currentQuantity - $requestedQuantity;

            if ($resultingQuantity > 9999999999) {
                throw ValidationException::withMessages([
                    'quantity' => 'Jumlah stok melebihi batas yang didukung.',
                ]);
            }

            $lockedItem->quantity = $this->minorUnitsToQuantity($resultingQuantity);
            $lockedItem->save();
            InventoryMovement::query()->create([
                'inventory_item_id' => $lockedItem->id,
                'type' => $validated['type'],
                'quantity' => $validated['quantity'],
                'reason' => $validated['reason'],
                'note' => $validated['note'] ?? null,
            ]);
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Penyesuaian stok '.$inventoryItem->name.' berhasil dicatat.',
        ]);
    }

    public function destroy(Inventory $inventoryItem): RedirectResponse
    {
        if ($inventoryItem->movements()->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Barang dengan riwayat pergerakan tidak dapat dihapus. Biarkan barang tetap tersimpan untuk menjaga riwayat.',
            ]);
        }

        $inventoryItem->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Barang inventaris berhasil dihapus.']);
    }

    public function destroySelected(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:inventory,id'],
        ]);

        $deletedCount = DB::transaction(function () use ($validated): int {
            $deletableIds = Inventory::query()
                ->whereIn('id', $validated['ids'])
                ->doesntHave('movements')
                ->lockForUpdate()
                ->pluck('id');

            if ($deletableIds->isEmpty()) {
                return 0;
            }

            return Inventory::query()->whereIn('id', $deletableIds)->delete();
        });

        if ($deletedCount < count($validated['ids'])) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => $deletedCount > 0
                    ? $deletedCount.' barang dihapus. Barang dengan riwayat pergerakan tetap disimpan.'
                    : 'Barang dengan riwayat pergerakan tidak dapat dihapus.',
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => $deletedCount.' barang inventaris berhasil dihapus.',
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function itemRules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:inventory_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'unit' => ['required', 'string', 'max:50'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:99999999.99'],
        ];
    }

    private function itemRow(Inventory $item): array
    {
        $isLow = (float) $item->quantity < (float) $item->minimum_stock;

        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'category' => $item->category,
            'category_id' => $item->category_id,
            'quantity' => $item->quantity,
            'unit' => $item->unit,
            'unit_cost' => $item->unit_cost,
            'minimum_stock' => $item->minimum_stock,
            'is_low_stock' => $isLow,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }

    private function quantityToMinorUnits(string|int|float $quantity): int
    {
        $parts = explode('.', (string) $quantity, 2);

        return ((int) $parts[0] * 100) + (int) str_pad(substr($parts[1] ?? '', 0, 2), 2, '0');
    }

    private function minorUnitsToQuantity(int $quantity): string
    {
        return intdiv($quantity, 100).'.'.str_pad((string) ($quantity % 100), 2, '0', STR_PAD_LEFT);
    }
}
