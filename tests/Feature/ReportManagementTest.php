<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\InventoryMovement;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'appointment_services',
            'appointments',
            'inventory_movements',
            'inventory',
            'inventory_categories',
            'services',
            'expenses',
            'expense_categories',
            'customers',
        ] as $table) {
            DB::table($table)->delete();
        }
    }

    public function test_reports_page_starts_without_a_generated_report(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('reports'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('report', null)
                ->has('options.services')
                ->has('options.customers')
                ->has('options.expense_categories')
                ->has('options.inventory_categories')
                ->has('options.inventory_items'));
    }

    public function test_financial_and_revenue_reports_use_completed_appointment_price_snapshots(): void
    {
        $this->travelTo('2026-10-20 12:00:00');
        $customer = Customer::query()->create(['name' => 'Lian']);
        $service = Service::query()->create([
            'name' => 'Gel manicure',
            'price' => 999999,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
        $completed = $this->createAppointment($customer, '2026-10-05', 'COMPLETED');
        $completed->services()->attach($service->id, [
            'price' => 150000,
            'duration_minutes' => 45,
        ]);
        $scheduled = $this->createAppointment($customer, '2026-10-06', 'SCHEDULED');
        $scheduled->services()->attach($service->id, [
            'price' => 200000,
            'duration_minutes' => 60,
        ]);
        $category = ExpenseCategory::query()->create(['name' => 'Utilities']);
        Expense::query()->create([
            'expense_category_id' => $category->id,
            'name' => 'Electricity',
            'amount' => 50000,
            'payment_method' => 'CASH',
            'expense_date' => '2026-10-05',
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('reports', [
                'report_type' => 'financial_summary',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.type', 'financial_summary')
                ->where('report.metrics.0.value', 150000)
                ->where('report.metrics.1.value', 50000)
                ->where('report.metrics.2.value', 100000)
                ->where('report.tables.0.rows.0.revenue', 150000));

        $this->get(route('reports', [
            'report_type' => 'revenue',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'service_id' => $service->id,
        ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.metrics.0.value', 150000)
                ->where('report.metrics.1.value', 1)
                ->where('report.metrics.2.value', 1)
                ->where('report.tables.0.rows.0.revenue', 150000)
                ->where('report.tables.0.rows.0.average_price', 150000));
    }

    public function test_expense_report_applies_date_category_and_payment_filters(): void
    {
        $category = ExpenseCategory::query()->create(['name' => 'Supplies']);
        Expense::query()->create([
            'expense_category_id' => $category->id,
            'name' => 'Polish order',
            'amount' => 125000,
            'payment_method' => 'QRIS',
            'expense_date' => '2026-10-12',
        ]);
        Expense::query()->create([
            'expense_category_id' => $category->id,
            'name' => 'Old order',
            'amount' => 25000,
            'payment_method' => 'CASH',
            'expense_date' => '2026-09-12',
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('reports', [
                'report_type' => 'expenses',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'expense_category_id' => $category->id,
                'payment_method' => 'QRIS',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.metrics.0.value', 125000)
                ->where('report.metrics.1.value', 1)
                ->where('report.tables.0.rows.0.name', 'Polish order')
                ->where('report.tables.1.rows.0.percentage', 100));
    }

    public function test_appointment_report_groups_each_database_status_by_date(): void
    {
        $customer = Customer::query()->create(['name' => 'Lian']);
        $this->createAppointment($customer, '2026-10-05', 'COMPLETED');
        $this->createAppointment($customer, '2026-10-05', 'NO_SHOW');
        $this->createAppointment($customer, '2026-10-06', 'CANCELLED');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('reports', [
                'report_type' => 'appointments',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.metrics.0.value', 3)
                ->where('report.metrics.2.value', 1)
                ->where('report.metrics.4.value', 1)
                ->where('report.tables.0.rows.0.date', '2026-10-05')
                ->where('report.tables.0.rows.0.total', 2)
                ->where('report.tables.0.rows.0.no_show', 1));
    }

    public function test_service_performance_report_uses_historical_prices_for_completed_bookings(): void
    {
        $customer = Customer::query()->create(['name' => 'Lian']);
        $service = Service::query()->create([
            'name' => 'Nail art',
            'price' => 999999,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
        $appointment = $this->createAppointment($customer, '2026-10-05', 'COMPLETED');
        $appointment->services()->attach($service->id, [
            'price' => 50000,
            'duration_minutes' => 30,
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('reports', [
                'report_type' => 'service_performance',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.metrics.0.value', 1)
                ->where('report.metrics.1.value', 50000)
                ->where('report.metrics.2.value', 'Nail art')
                ->where('report.tables.0.rows.0.average_price', 50000));
    }

    public function test_customer_report_sums_completed_service_snapshots_and_period_activity(): void
    {
        $this->travelTo('2026-10-20 12:00:00');
        $customer = Customer::query()->create(['name' => 'Lian', 'created_at' => '2026-10-02 10:00:00']);
        $service = Service::query()->create([
            'name' => 'Manicure',
            'price' => 100000,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
        $appointment = $this->createAppointment($customer, '2026-10-05', 'COMPLETED');
        $appointment->services()->attach($service->id, [
            'price' => 80000,
            'duration_minutes' => 30,
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('reports', [
                'report_type' => 'customers',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'customer_id' => $customer->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.metrics.1.value', 1)
                ->where('report.metrics.2.value', 1)
                ->where('report.metrics.3.value', 1)
                ->where('report.tables.0.rows.0.total_spent', 80000));
    }

    public function test_inventory_status_marks_low_stock_and_values_stock_from_quantity_times_unit_cost(): void
    {
        $category = InventoryCategory::query()->create(['name' => 'Polish']);
        $item = new Inventory([
            'category_id' => $category->id,
            'name' => 'Gel polish',
            'unit' => 'bottle',
            'unit_cost' => '25000.00',
            'minimum_stock' => '3.00',
        ]);
        $item->quantity = '2.00';
        $item->save();

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('reports', [
                'report_type' => 'inventory_status',
                'inventory_category_id' => $category->id,
                'stock_status' => 'low_stock',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.metrics.0.value', 1)
                ->where('report.metrics.1.value', 1)
                ->where('report.metrics.2.value', 50000)
                ->where('report.tables.0.rows.0.status', 'Stok menipis'));
    }

    public function test_stock_movement_report_keeps_incomparable_units_separate(): void
    {
        $category = InventoryCategory::query()->create(['name' => 'Supplies']);
        foreach ([['Polish', 'bottle'], ['Remover', 'ml']] as [$name, $unit]) {
            $item = new Inventory([
                'category_id' => $category->id,
                'name' => $name,
                'unit' => $unit,
                'unit_cost' => 100,
                'minimum_stock' => 0,
            ]);
            $item->quantity = 0;
            $item->save();
            InventoryMovement::query()->create([
                'inventory_item_id' => $item->id,
                'type' => 'IN',
                'quantity' => '2.00',
                'reason' => 'Purchase',
                'created_at' => '2026-10-05 12:00:00',
            ]);
        }

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('reports', [
                'report_type' => 'stock_movements',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'inventory_category_id' => $category->id,
                'movement_type' => 'IN',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.metrics.0.value', 2)
                ->has('report.metrics.2.value', 2)
                ->where('report.metrics.2.value.0.quantity', 2)
                ->where('report.metrics.2.value.1.quantity', 2)
                ->where('report.tables.0.rows.0.reason', 'Purchase'));
    }

    public function test_csv_and_xlsx_exports_are_downloads_using_the_selected_report_filters(): void
    {
        $category = ExpenseCategory::query()->create(['name' => 'Supplies']);
        Expense::query()->create([
            'expense_category_id' => $category->id,
            'name' => 'Filtered supplies',
            'amount' => 12345,
            'payment_method' => 'QRIS',
            'expense_date' => '2026-10-12',
        ]);
        Expense::query()->create([
            'expense_category_id' => $category->id,
            'name' => 'Outside period',
            'amount' => 67890,
            'payment_method' => 'CASH',
            'expense_date' => '2026-09-12',
        ]);

        $csvResponse = $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('reports.export', [
                'format' => 'csv',
                'report_type' => 'expenses',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'expense_category_id' => $category->id,
            ]))
            ->assertOk()
            ->assertDownload('laporan-pengeluaran-2026-10-01-to-2026-10-31.csv');
        $csv = $csvResponse->streamedContent();
        $this->assertStringContainsString('Filtered supplies', $csv);
        $this->assertStringNotContainsString('Outside period', $csv);

        $workbookResponse = $this->get(route('reports.export', [
            'format' => 'xlsx',
            'report_type' => 'expenses',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]))
            ->assertOk()
            ->assertDownload('laporan-pengeluaran-2026-10-01-to-2026-10-31.xlsx');
        $workbookPath = tempnam(sys_get_temp_dir(), 'nails-report-test-');
        $this->assertNotFalse($workbookPath);
        file_put_contents($workbookPath, $workbookResponse->streamedContent());
        $workbook = new \ZipArchive;

        try {
            $this->assertTrue($workbook->open($workbookPath) === true);
            $this->assertStringContainsString('Data laporan', $workbook->getFromName('xl/workbook.xml'));
            $this->assertStringContainsString('Filtered supplies', $workbook->getFromName('xl/worksheets/sheet2.xml'));
        } finally {
            $workbook->close();
            unlink($workbookPath);
        }

        $this->get(route('reports.print', [
            'report_type' => 'expenses',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]))
            ->assertOk()
            ->assertSee('Cetak / Simpan sebagai PDF')
            ->assertSee('Filtered supplies');
    }

    public function test_xlsx_exports_are_available_for_every_report_type(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin');
        $reportTypes = [
            'financial_summary',
            'revenue',
            'expenses',
            'appointments',
            'service_performance',
            'customers',
            'inventory_status',
            'stock_movements',
        ];

        foreach ($reportTypes as $reportType) {
            $response = $this->get(route('reports.export', [
                'format' => 'xlsx',
                'report_type' => $reportType,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ]));

            $response->assertOk()
                ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->assertStringStartsWith('PK', $response->streamedContent(), $reportType);
        }
    }

    public function test_report_date_validation_and_guest_access_are_enforced(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('reports', [
                'report_type' => 'financial_summary',
                'start_date' => '2026-10-20',
                'end_date' => '2026-10-01',
            ]))->assertSessionHasErrors('end_date');

        auth('admin')->logout();

        $this->get(route('reports.export', ['format' => 'csv']))
            ->assertRedirect(route('login'));
    }

    private function demoAdmin(): Admin
    {
        return Admin::query()->where('email', 'admin123@gmail.com')->firstOrFail();
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
