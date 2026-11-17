<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $supplies = ExpenseCategory::query()->firstOrCreate(
            ['name' => 'Supplies'],
            ['description' => 'Business supplies and consumables.'],
        );
        $utilities = ExpenseCategory::query()->firstOrCreate(
            ['name' => 'Utilities'],
            ['description' => 'Electricity, water, and internet.'],
        );
        $operations = ExpenseCategory::query()->firstOrCreate(
            ['name' => 'Operations'],
            ['description' => 'General operating costs.'],
        );

        Expense::factory()->create([
            'expense_category_id' => $utilities->id,
            'name' => 'Electricity Bill',
            'amount' => 350000,
            'payment_method' => 'BANK TRANSFER',
            'expense_date' => now()->toDateString(),
            'note' => 'Monthly electricity bill.',
        ]);
        Expense::factory()->create([
            'expense_category_id' => $supplies->id,
            'name' => 'Nail Supply Purchase',
            'amount' => 150000,
            'payment_method' => 'CASH',
            'expense_date' => now()->subDay()->toDateString(),
            'note' => 'Restocked salon supplies.',
        ]);
        Expense::factory()->create([
            'expense_category_id' => $operations->id,
            'name' => 'Delivery',
            'amount' => 50000,
            'payment_method' => 'QRIS',
            'expense_date' => now()->subDays(2)->toDateString(),
            'note' => 'Supply delivery.',
        ]);
    }
}
