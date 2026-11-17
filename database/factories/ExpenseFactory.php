<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'name' => fake()->words(2, true),
            'amount' => fake()->randomFloat(2, 1, 1000000),
            'payment_method' => fake()->randomElement(['CASH', 'BANK TRANSFER', 'QRIS', 'OTHER']),
            'expense_date' => fake()->date(),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
