<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $category = InventoryCategory::query()->create($request->validate($this->categoryRules()));

        return back()->with('toast', ['type' => 'success', 'message' => 'Kategori '.$category->name.' berhasil ditambahkan.']);
    }

    public function quickStore(Request $request): JsonResponse
    {
        $category = InventoryCategory::query()->create($request->validate($this->categoryRules()));

        return response()->json([
            'category' => $category->only(['id', 'name', 'description']),
        ], 201);
    }

    public function update(Request $request, InventoryCategory $inventoryCategory): RedirectResponse
    {
        $inventoryCategory->update($request->validate($this->categoryRules($inventoryCategory)));

        return back()->with('toast', ['type' => 'success', 'message' => 'Kategori '.$inventoryCategory->name.' berhasil diperbarui.']);
    }

    public function destroy(InventoryCategory $inventoryCategory): RedirectResponse
    {
        if ($inventoryCategory->items()->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Kategori masih digunakan barang inventaris. Pindahkan atau hapus barang terlebih dahulu.',
            ]);
        }

        $inventoryCategory->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Kategori inventaris berhasil dihapus.']);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function categoryRules(?InventoryCategory $inventoryCategory = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_categories', 'name')->ignore($inventoryCategory?->id),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
