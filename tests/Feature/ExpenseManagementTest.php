<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_quick_create_an_expense_category_from_the_expense_form(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->postJson(route('expenses.categories.quick-store'), [
                'name' => 'Quick category',
                'description' => null,
            ])
            ->assertCreated()
            ->assertJsonPath('category.name', 'Quick category');
    }

    public function test_expense_page_includes_filtered_totals_categories_and_pagination(): void
    {
        $this->travelTo('2026-10-04 14:00:00');
        $category = $this->createCategory('Utilities');
        Expense::factory()->count(11)->create([
            'expense_category_id' => $category->id,
            'amount' => 1000,
            'payment_method' => 'QRIS',
            'expense_date' => '2026-10-03',
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('expenses', [
                'category_id' => $category->id,
                'payment_method' => 'QRIS',
                'date_filter' => 'custom',
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-04',
                'per_page' => 10,
                'page' => 2,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Expenses/Index')
                ->where('filters.tab', 'expenses')
                ->where('expenses.total', 11)
                ->where('expenses.from', 11)
                ->where('summary.count', 11)
                ->where('summary.total', 11000)
                ->where('summary.this_month', 11000)
                ->where('categories.0.name', 'Utilities'));
    }

    public function test_expenses_can_be_filtered_by_name_category_payment_and_date_range(): void
    {
        $this->travelTo('2026-10-04 14:00:00');
        $category = $this->createCategory('Utilities');
        Expense::factory()->create([
            'expense_category_id' => $category->id,
            'name' => 'October electricity',
            'amount' => 350000,
            'payment_method' => 'BANK TRANSFER',
            'expense_date' => '2026-10-03',
        ]);
        Expense::factory()->create([
            'expense_category_id' => $category->id,
            'name' => 'September electricity',
            'amount' => 100000,
            'payment_method' => 'CASH',
            'expense_date' => '2026-09-30',
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('expenses', [
                'search' => 'October',
                'category_id' => $category->id,
                'payment_method' => 'BANK TRANSFER',
                'date_filter' => 'custom',
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-04',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('expenses.total', 1)
                ->where('expenses.data.0.name', 'October electricity')
                ->where('summary.total', 350000)
                ->where('summary.this_month', 350000));
    }

    public function test_expenses_can_be_filtered_by_today_this_week_and_this_month(): void
    {
        $this->travelTo('2026-10-04 14:00:00');
        $category = $this->createCategory('Date filter category');
        foreach ([
            ['Today', '2026-10-04'],
            ['This week', '2026-09-28'],
            ['Last week', '2026-09-27'],
            ['This month', '2026-10-01'],
        ] as [$name, $date]) {
            Expense::factory()->create([
                'expense_category_id' => $category->id,
                'name' => $name,
                'expense_date' => $date,
            ]);
        }

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('expenses', ['category_id' => $category->id, 'date_filter' => 'today']))
            ->assertInertia(fn (Assert $page) => $page->where('expenses.total', 1));

        $this->get(route('expenses', ['category_id' => $category->id, 'date_filter' => 'this_week']))
            ->assertInertia(fn (Assert $page) => $page->where('expenses.total', 2));

        $this->get(route('expenses', ['category_id' => $category->id, 'date_filter' => 'this_month']))
            ->assertInertia(fn (Assert $page) => $page->where('expenses.total', 2));
    }

    public function test_admin_can_create_and_update_an_expense(): void
    {
        $category = $this->createCategory('Utilities');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'name' => 'Electricity bill',
                'amount' => '350000.00',
                'payment_method' => 'BANK TRANSFER',
                'expense_date' => '2026-10-03',
                'note' => 'Monthly bill',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'success');

        $expense = Expense::query()->where('name', 'Electricity bill')->firstOrFail();
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'expense_category_id' => $category->id,
            'amount' => '350000.00',
            'payment_method' => 'BANK TRANSFER',
        ]);
        $this->assertSame('2026-10-03', $expense->fresh()->expense_date->toDateString());

        $this->put(route('expenses.update', $expense), [
            'expense_category_id' => $category->id,
            'name' => 'Water bill',
            'amount' => '125000.00',
            'payment_method' => 'QRIS',
            'expense_date' => '2026-10-04',
            'note' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'name' => 'Water bill',
            'amount' => '125000.00',
            'payment_method' => 'QRIS',
            'note' => null,
        ]);
        $this->assertSame('2026-10-04', $expense->fresh()->expense_date->toDateString());
    }

    public function test_expense_creation_rejects_zero_amount_invalid_payment_method_and_invalid_date(): void
    {
        $category = $this->createCategory('Utilities');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('expenses'))
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'name' => 'Invalid expense',
                'amount' => '0.00',
                'payment_method' => 'CHEQUE',
                'expense_date' => 'not-a-date',
            ])
            ->assertRedirect(route('expenses'))
            ->assertSessionHasErrors(['amount', 'payment_method', 'expense_date']);

        $this->assertDatabaseMissing('expenses', ['name' => 'Invalid expense']);
    }

    public function test_expense_details_return_category_and_timestamp_fields(): void
    {
        $expense = Expense::factory()->create([
            'name' => 'Electricity bill',
            'note' => 'Supplier note',
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->getJson(route('expenses.show', $expense))
            ->assertOk()
            ->assertJsonPath('expense.name', 'Electricity bill')
            ->assertJsonPath('expense.category.name', $expense->category->name)
            ->assertJsonPath('expense.note', 'Supplier note')
            ->assertJsonStructure(['expense' => ['created_at', 'updated_at']]);
    }

    public function test_expense_categories_can_be_created_updated_and_must_be_unique(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->post(route('expenses.categories.store'), [
                'name' => 'Transportation',
                'description' => 'Delivery costs',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'success');

        $category = ExpenseCategory::query()->where('name', 'Transportation')->firstOrFail();
        $this->put(route('expenses.categories.update', $category), [
            'name' => 'Transport',
            'description' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'Transport',
            'description' => null,
        ]);

        $this->from(route('expenses', ['tab' => 'categories']))
            ->post(route('expenses.categories.store'), ['name' => 'Transport'])
            ->assertRedirect(route('expenses', ['tab' => 'categories']))
            ->assertSessionHasErrors('name');
    }

    public function test_expense_category_with_expenses_cannot_be_deleted(): void
    {
        $category = $this->createCategory('Utilities');
        Expense::factory()->create(['expense_category_id' => $category->id]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->delete(route('expenses.categories.destroy', $category))
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'error');

        $this->assertModelExists($category);
    }

    public function test_admin_can_delete_an_expense_and_bulk_delete_selected_expenses(): void
    {
        $singleExpense = Expense::factory()->create(['name' => 'Single expense']);
        $bulkExpenses = Expense::factory()->count(2)->create();

        $this->actingAs($this->demoAdmin(), 'admin')
            ->delete(route('expenses.destroy', $singleExpense))
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'success');

        $this->assertModelMissing($singleExpense);

        $this->delete(route('expenses.destroy-selected'), [
            'ids' => $bulkExpenses->modelKeys(),
        ])
            ->assertRedirect()
            ->assertSessionHas('toast.message', '2 pengeluaran berhasil dihapus.');

        foreach ($bulkExpenses as $expense) {
            $this->assertModelMissing($expense);
        }
    }

    public function test_bulk_expense_deletion_rejects_duplicate_ids(): void
    {
        $expense = Expense::factory()->create();

        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('expenses'))
            ->delete(route('expenses.destroy-selected'), [
                'ids' => [$expense->id, $expense->id],
            ])
            ->assertRedirect(route('expenses'))
            ->assertSessionHasErrors('ids.1');

        $this->assertModelExists($expense);
    }

    public function test_guest_is_redirected_from_expense_management(): void
    {
        $this->get(route('expenses'))->assertRedirect(route('login'));
    }

    private function demoAdmin(): Admin
    {
        return Admin::query()->where('email', 'admin123@gmail.com')->firstOrFail();
    }

    private function createCategory(string $name): ExpenseCategory
    {
        return ExpenseCategory::query()->create(['name' => $name]);
    }
}
