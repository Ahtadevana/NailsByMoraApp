<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    private const PAYMENT_METHODS = ['CASH', 'BANK TRANSFER', 'QRIS', 'OTHER'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['expenses', 'categories'])],
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'payment_method' => ['nullable', Rule::in(['all', ...self::PAYMENT_METHODS])],
            'date_filter' => ['nullable', Rule::in(['all', 'today', 'this_week', 'this_month', 'custom'])],
            'date_from' => ['nullable', 'required_if:date_filter,custom', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_if:date_filter,custom', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name_asc', 'name_desc', 'amount_asc', 'amount_desc', 'date_asc', 'date_desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ]);

        $tab = $filters['tab'] ?? 'expenses';
        $perPage = (int) ($filters['per_page'] ?? 10);
        $categories = ExpenseCategory::query()
            ->withCount('expenses')
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        $query = Expense::query()->with('category:id,name');
        $this->applyFilters($query, $filters);

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'amount_asc' => $query->orderBy('amount')->orderBy('id'),
            'amount_desc' => $query->orderByDesc('amount')->orderByDesc('id'),
            'date_asc' => $query->orderBy('expense_date')->orderBy('id'),
            'date_desc' => $query->orderByDesc('expense_date')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        $filteredExpenses = clone $query;
        $expenses = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Expense $expense): array => $this->expenseRow($expense));

        $thisMonth = clone $filteredExpenses;
        $thisMonth->whereBetween('expense_date', [
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        ]);

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'categories' => $categories,
            'filters' => [
                'tab' => $tab,
                'search' => $filters['search'] ?? '',
                'category_id' => $filters['category_id'] ?? '',
                'payment_method' => $filters['payment_method'] ?? 'all',
                'date_filter' => $filters['date_filter'] ?? 'all',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'sort' => $filters['sort'] ?? 'newest',
                'per_page' => $perPage,
            ],
            'summary' => [
                'total' => (float) $filteredExpenses->sum('amount'),
                'this_month' => (float) $thisMonth->sum('amount'),
                'count' => (int) $filteredExpenses->count(),
            ],
        ]);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json([
            'expense' => $this->expenseRow($expense->load('category:id,name')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $expense = Expense::query()->create($request->validate($this->expenseRules()));

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pengeluaran '.$expense->name.' berhasil ditambahkan.',
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $expense->update($request->validate($this->expenseRules()));

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pengeluaran '.$expense->name.' berhasil diperbarui.',
        ]);
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pengeluaran berhasil dihapus.',
        ]);
    }

    public function destroySelected(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:expenses,id'],
        ]);

        $deletedCount = Expense::query()->whereIn('id', $validated['ids'])->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $deletedCount.' pengeluaran berhasil dihapus.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['category_id'])) {
            $query->where('expense_category_id', $filters['category_id']);
        }

        if (($filters['payment_method'] ?? 'all') !== 'all') {
            $query->where('payment_method', $filters['payment_method']);
        }

        match ($filters['date_filter'] ?? 'all') {
            'today' => $query->whereDate('expense_date', today()),
            'this_week' => $query->whereBetween('expense_date', [
                CarbonImmutable::now()->startOfWeek()->toDateString(),
                CarbonImmutable::now()->endOfWeek()->toDateString(),
            ]),
            'this_month' => $query->whereBetween('expense_date', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ]),
            'custom' => $query->whereBetween('expense_date', [$filters['date_from'], $filters['date_to']]),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function expenseRow(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'name' => $expense->name,
            'category' => $expense->category,
            'expense_category_id' => $expense->expense_category_id,
            'amount' => $expense->amount,
            'payment_method' => $expense->payment_method,
            'expense_date' => $expense->expense_date->toDateString(),
            'note' => $expense->note,
            'created_at' => $expense->created_at,
            'updated_at' => $expense->updated_at,
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function expenseRules(): array
    {
        return [
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
            'payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
