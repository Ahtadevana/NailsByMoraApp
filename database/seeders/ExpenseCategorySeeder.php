<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Utilities', 'Electricity, water, and internet.'],
            ['Supplies', 'Business supplies and consumables.'],
            ['Transportation', 'Delivery and transportation costs.'],
        ] as [$name, $description]) {
            ExpenseCategory::query()->firstOrCreate(
                ['name' => $name],
                ['description' => $description],
            );
        }
    }
}
