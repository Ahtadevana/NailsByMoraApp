<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IncomeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('appointment_services')->delete();
        DB::table('appointments')->delete();
        DB::table('customers')->delete();
        DB::table('services')->delete();
    }

    public function test_income_log_sums_completed_services_and_excludes_unfinished_appointments(): void
    {
        $customer = Customer::query()->create(['name' => 'Nadia']);
        $service = $this->createService('Gel polish', 85000);
        $completed = $this->createAppointment($customer, '2026-10-08', 'COMPLETED');
        $completed->services()->attach($service->id, [
            'price' => 85000,
            'duration_minutes' => 60,
        ]);
        $scheduled = $this->createAppointment($customer, '2026-10-09', 'SCHEDULED');
        $scheduled->services()->attach($service->id, [
            'price' => 85000,
            'duration_minutes' => 60,
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('incomes', [
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-31',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Incomes/Index')
                ->where('summary.amount', 85000)
                ->where('summary.count', 1)
                ->where('incomes.data.0.customer_name', 'Nadia')
                ->where('incomes.data.0.service_name', 'Gel polish')
                ->where('incomes.data.0.appointment_date', '2026-10-08'));
    }

    private function demoAdmin(): Admin
    {
        return Admin::query()->where('email', 'admin123@gmail.com')->firstOrFail();
    }

    private function createService(string $name, int $price): Service
    {
        return Service::query()->create([
            'name' => $name,
            'price' => $price,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
    }

    private function createAppointment(Customer $customer, string $date, string $status): Appointment
    {
        return Appointment::query()->create([
            'customer_id' => $customer->id,
            'appointment_date' => $date,
            'start_time' => '10:00',
            'status' => $status,
        ]);
    }
}
