<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $category = ExpenseCategory::query()->create($request->validate($this->categoryRules()));

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Kategori '.$category->name.' berhasil ditambahkan.',
        ]);
    }

    public function quickStore(Request $request): JsonResponse
    {
        $category = ExpenseCategory::query()->create($request->validate($this->categoryRules()));

        return response()->json([
            'category' => $category->only(['id', 'name', 'description']),
        ], 201);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->update($request->validate($this->categoryRules($expenseCategory)));

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Kategori '.$expenseCategory->name.' berhasil diperbarui.',
        ]);
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        if ($expenseCategory->expenses()->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Kategori masih digunakan pengeluaran. Pindahkan atau hapus pengeluaran terlebih dahulu.',
            ]);
        }

        $expenseCategory->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Kategori pengeluaran berhasil dihapus.',
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function categoryRules(?ExpenseCategory $expenseCategory = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('expense_categories', 'name')->ignore($expenseCategory?->id),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
