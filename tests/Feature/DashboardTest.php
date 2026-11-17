<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_period_totals_today_schedule_and_current_stock_alerts(): void
    {
        $this->travelTo('2026-10-04 15:34:00');
        $customer = Customer::query()->create(['name' => 'Mira']);
        $customer->created_at = '2025-10-02 09:00:00';
        $customer->save();
        $service = Service::query()->create([
            'name' => 'Gel manicure',
            'price' => 999999,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
        $completed = $this->createAppointment($customer, '2025-10-05', 'COMPLETED');
        $completed->services()->attach($service->id, [
            'price' => 150000,
            'duration_minutes' => 45,
        ]);
        $scheduled = $this->createAppointment($customer, '2025-10-06', 'SCHEDULED');
        $today = $this->createAppointment($customer, '2026-10-04', 'SCHEDULED', '14:00:00');
        $today->services()->attach($service->id, [
            'price' => 999999,
            'duration_minutes' => 60,
        ]);
        $category = ExpenseCategory::query()->create(['name' => 'Supplies']);
        Expense::query()->create([
            'expense_category_id' => $category->id,
            'name' => 'Nail files',
            'amount' => 50000,
            'payment_method' => 'CASH',
            'expense_date' => '2025-10-10',
        ]);
        $inventoryCategory = InventoryCategory::query()->create(['name' => 'Polish']);
        $lowStockItem = new Inventory([
            'category_id' => $inventoryCategory->id,
            'name' => 'Base coat',
            'unit' => 'bottle',
            'unit_cost' => 20000,
            'minimum_stock' => 3,
        ]);
        $lowStockItem->quantity = 1;
        $lowStockItem->save();

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('dashboard', [
                'period' => 'custom',
                'start_date' => '2025-10-01',
                'end_date' => '2025-10-31',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('period.label', '1 Okt 2025 – 31 Okt 2025')
                ->where('financial.revenue', 150000)
                ->where('financial.expenses', 50000)
                ->where('financial.net_income', 100000)
                ->where('financial.appointments.total', 2)
                ->where('financial.appointments.completed', 1)
                ->where('financial.appointments.scheduled', 1)
                ->where('chart.datasets.0.values.4', 150000)
                ->where('chart.datasets.1.values.9', 50000)
                ->where('todayAppointmentCount', 1)
                ->where('todayAppointments.0.customer', 'Mira')
                ->where('todayAppointments.0.services.0', 'Gel manicure')
                ->where('attention.low_stock_count', 1)
                ->where('attention.low_stock_items.0.name', 'Base coat')
                ->where('activity.customers', 1)
                ->where('activity.new_customers', 1)
                ->where('activity.active_services', 1)
                ->where('activity.upcoming_appointments', 0));
    }

    public function test_dashboard_counts_each_appointment_status_in_a_custom_period(): void
    {
        $customer = Customer::query()->create(['name' => 'Status customer']);
        foreach (['SCHEDULED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'] as $status) {
            $this->createAppointment($customer, '2090-06-15', $status);
        }

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('dashboard', [
                'period' => 'custom',
                'start_date' => '2090-06-01',
                'end_date' => '2090-06-30',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('financial.appointments.total', 4)
                ->where('financial.appointments.scheduled', 1)
                ->where('financial.appointments.completed', 1)
                ->where('financial.appointments.cancelled', 1)
                ->where('financial.appointments.no_show', 1));
    }

    public function test_dashboard_rejects_a_custom_date_range_that_ends_before_it_starts(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('dashboard', [
                'period' => 'custom',
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-01',
            ]))
            ->assertSessionHasErrors('end_date');
    }

    public function test_guest_is_redirected_to_the_login_page_from_dashboard(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    private function demoAdmin(): Admin
    {
        return Admin::query()->where('email', 'admin123@gmail.com')->firstOrFail();
    }

    private function createAppointment(
        Customer $customer,
        string $date,
        string $status,
        string $startTime = '10:00:00',
    ): Appointment {
        return Appointment::query()->create([
            'customer_id' => $customer->id,
            'appointment_date' => $date,
            'start_time' => $startTime,
            'status' => $status,
        ]);
    }
}
