<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppointmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointments_page_includes_calendar_counts_and_booking_summaries(): void
    {
        $this->travelTo('2026-10-03 12:00:00');
        $customer = Customer::query()->create(['name' => 'Lian']);
        $service = $this->createService('Gel manicure', 150000, 60);
        $appointment = $this->createAppointment($customer, '2026-10-05', '14:00:00', 'SCHEDULED');
        $appointment->services()->attach($service->id, [
            'price' => 150000,
            'duration_minutes' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('appointments', ['month' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Appointments/Index')
                ->where('calendar.month', '2026-10')
                ->where('calendar.counts.2026-10-05', 1)
                ->where('summary.today', 0)
                ->where('summary.scheduled', 1)
                ->where('summary.no_show', 0)
                ->has('appointments.data', 1)
                ->where('appointments.data.0.customer.name', 'Lian'));
    }

    public function test_appointments_can_be_filtered_by_selected_date_status_customer_search_and_range(): void
    {
        $customer = Customer::query()->create(['name' => 'Lian']);
        $service = $this->createService('Nail art', 50000, 30);
        $appointment = $this->createAppointment($customer, '2026-10-07', '14:00:00', 'NO_SHOW');
        $appointment->services()->attach($service->id, [
            'price' => 50000,
            'duration_minutes' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('appointments', [
                'month' => '2026-10',
                'date' => '2026-10-07',
                'status' => 'NO_SHOW',
                'customer_id' => $customer->id,
                'search' => 'Lian',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.date', '2026-10-07')
                ->where('filters.status', 'NO_SHOW')
                ->where('appointments.total', 1)
                ->where('appointments.data.0.status', 'NO_SHOW')
                ->where('appointments.data.0.customer.name', 'Lian'));

        $this->get(route('appointments', [
            'month' => '2026-10',
            'date_from' => '2026-10-06',
            'date_to' => '2026-10-08',
            'per_page' => 25,
        ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.per_page', 25)
                ->where('appointments.per_page', 25)
                ->has('appointments.data', 1));
    }

    public function test_customer_lookup_searches_names_without_returning_unmatched_customers(): void
    {
        Customer::query()->create(['name' => 'Lian']);
        Customer::query()->create(['name' => 'Mia']);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->getJson(route('appointments.lookups', ['search' => 'Lia']))
            ->assertOk()
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.name', 'Lian');
    }

    public function test_admin_can_quick_create_a_customer_and_service_from_an_appointment_form(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->postJson(route('appointments.customers.quick-store'), [
                'name' => 'Quick customer',
                'phone' => '08123456789',
                'email' => 'quick@example.test',
            ])
            ->assertCreated()
            ->assertJsonPath('customer.name', 'Quick customer')
            ->assertJsonPath('customer.phone', '08123456789');

        $this->postJson(route('appointments.services.quick-store'), [
            'name' => 'Quick service',
            'price' => 45000,
            'duration_minutes' => 45,
        ])
            ->assertCreated()
            ->assertJsonPath('service.name', 'Quick service')
            ->assertJsonPath('service.price', '45000.00')
            ->assertJsonPath('service.is_active', true);
    }

    public function test_quick_customer_creation_rejects_a_name_with_symbols(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->postJson(route('appointments.customers.quick-store'), [
                'name' => 'Quick @ customer',
                'email' => 'quick@example.test',
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.name.0',
                'Nama hanya boleh berisi huruf dan spasi; angka dan simbol tidak diperbolehkan.',
            );

        $this->assertDatabaseMissing('customers', ['name' => 'Quick @ customer']);
    }

    public function test_admin_can_create_an_appointment_with_price_and_duration_snapshots(): void
    {
        $this->travelTo('2026-10-04 12:00:00');
        $customer = Customer::query()->create(['name' => 'Lian']);
        $gel = $this->createService('Gel manicure', 150000, 60);
        $art = $this->createService('Nail art', 50000, 30);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('appointments', ['month' => '2026-10']))
            ->post(route('appointments.store'), [
                'customer_id' => $customer->id,
                'appointment_date' => '2026-10-05',
                'start_time' => '14:00',
                'status' => 'SCHEDULED',
                'notes' => 'Please keep the design simple.',
                'services' => [['id' => $gel->id], ['id' => $art->id]],
            ])
            ->assertRedirect(route('appointments', ['month' => '2026-10']));

        $appointment = Appointment::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $gel->id,
            'price' => '150000.00',
            'duration_minutes' => 60,
        ]);
        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $art->id,
            'price' => '50000.00',
            'duration_minutes' => 30,
        ]);
        $this->assertSame('Janji temu berhasil dibuat.', session('toast.message'));
    }

    public function test_admin_cannot_create_an_appointment_that_overlaps_a_scheduled_appointment(): void
    {
        $this->travelTo('2026-10-04 12:00:00');
        $customer = Customer::query()->create(['name' => 'Lian']);
        $hourService = $this->createService('Gel manicure', 150000, 60);
        $halfHourService = $this->createService('Nail art', 50000, 30);
        $existing = $this->createAppointment($customer, '2026-10-05', '14:00:00');
        $existing->services()->attach($hourService->id, [
            'price' => 150000,
            'duration_minutes' => 60,
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('appointments'))
            ->post(route('appointments.store'), [
                'customer_id' => $customer->id,
                'appointment_date' => '2026-10-05',
                'start_time' => '14:30',
                'status' => 'SCHEDULED',
                'services' => [['id' => $halfHourService->id]],
            ])
            ->assertSessionHasErrors([
                'start_time' => 'Waktu ini bertabrakan dengan jadwal janji temu lainnya. Pilih waktu lain.',
            ]);

        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_admin_can_create_an_appointment_starting_when_another_appointment_ends(): void
    {
        $this->travelTo('2026-10-04 12:00:00');
        $customer = Customer::query()->create(['name' => 'Lian']);
        $service = $this->createService('Gel manicure', 150000, 60);
        $existing = $this->createAppointment($customer, '2026-10-05', '14:00:00');
        $existing->services()->attach($service->id, [
            'price' => 150000,
            'duration_minutes' => 60,
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->post(route('appointments.store'), [
                'customer_id' => $customer->id,
                'appointment_date' => '2026-10-05',
                'start_time' => '15:00',
                'status' => 'SCHEDULED',
                'services' => [['id' => $service->id]],
            ])
            ->assertRedirect();

        $this->assertSame(2, Appointment::query()->count());
    }

    public function test_admin_cannot_update_an_appointment_to_overlap_a_scheduled_appointment(): void
    {
        $this->travelTo('2026-10-04 12:00:00');
        $customer = Customer::query()->create(['name' => 'Lian']);
        $hourService = $this->createService('Gel manicure', 150000, 60);
        $halfHourService = $this->createService('Nail art', 50000, 30);
        $existing = $this->createAppointment($customer, '2026-10-05', '14:00:00');
        $existing->services()->attach($hourService->id, [
            'price' => 150000,
            'duration_minutes' => 60,
        ]);
        $appointment = $this->createAppointment($customer, '2026-10-05', '16:00:00');
        $appointment->services()->attach($halfHourService->id, [
            'price' => 50000,
            'duration_minutes' => 30,
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('appointments'))
            ->put(route('appointments.update', $appointment), [
                'customer_id' => $customer->id,
                'appointment_date' => '2026-10-05',
                'start_time' => '14:30',
                'status' => 'SCHEDULED',
                'services' => [['id' => $halfHourService->id]],
            ])
            ->assertSessionHasErrors([
                'start_time' => 'Waktu ini bertabrakan dengan jadwal janji temu lainnya. Pilih waktu lain.',
            ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'start_time' => '16:00:00',
        ]);
    }

    public function test_admin_cannot_reactivate_an_appointment_that_overlaps_a_scheduled_appointment(): void
    {
        $this->travelTo('2026-10-04 12:00:00');
        $customer = Customer::query()->create(['name' => 'Lian']);
        $service = $this->createService('Gel manicure', 150000, 60);
        $existing = $this->createAppointment($customer, '2026-10-05', '14:00:00');
        $existing->services()->attach($service->id, [
            'price' => 150000,
            'duration_minutes' => 60,
        ]);
        $cancelled = $this->createAppointment($customer, '2026-10-05', '14:30:00', 'CANCELLED');
        $cancelled->services()->attach($service->id, [
            'price' => 150000,
            'duration_minutes' => 60,
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('appointments'))
            ->patch(route('appointments.update-status', $cancelled), ['status' => 'SCHEDULED'])
            ->assertSessionHasErrors([
                'status' => 'Waktu ini bertabrakan dengan jadwal janji temu lainnya. Pilih waktu lain.',
            ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $cancelled->id,
            'status' => 'CANCELLED',
        ]);
    }

    public function test_details_calculate_totals_end_time_and_return_booking_snapshots(): void
    {
        $customer = Customer::query()->create(['name' => 'Lian', 'phone' => '08123456789']);
        $gel = $this->createService('Gel manicure', 300000, 90);
        $art = $this->createService('Nail art', 100000, 30);
        $appointment = $this->createAppointment($customer, '2026-10-05', '14:00:00');
        $appointment->services()->attach([
            $gel->id => ['price' => 150000, 'duration_minutes' => 60, 'created_at' => now(), 'updated_at' => now()],
            $art->id => ['price' => 50000, 'duration_minutes' => 30, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->getJson(route('appointments.show', $appointment))
            ->assertOk()
            ->assertJsonPath('appointment.customer.name', 'Lian')
            ->assertJsonPath('appointment.start_time', '14:00')
            ->assertJsonPath('appointment.end_time', '15:30')
            ->assertJsonPath('appointment.total_price', 200000)
            ->assertJsonPath('appointment.total_duration', 90)
            ->assertJsonPath('appointment.services.0.price', 150000);
    }

    public function test_updating_an_appointment_keeps_existing_service_snapshots_and_snapshots_new_services(): void
    {
        $this->travelTo('2026-10-04 12:00:00');
        $customer = Customer::query()->create(['name' => 'Lian']);
        $existing = $this->createService('Gel manicure', 300000, 90);
        $added = $this->createService('Nail art', 80000, 40);
        $appointment = $this->createAppointment($customer, '2026-10-05', '14:00:00');
        $appointment->services()->attach($existing->id, [
            'price' => 150000,
            'duration_minutes' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->put(route('appointments.update', $appointment), [
                'customer_id' => $customer->id,
                'appointment_date' => '2026-10-06',
                'start_time' => '15:00',
                'status' => 'SCHEDULED',
                'notes' => null,
                'services' => [['id' => $existing->id], ['id' => $added->id]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $existing->id,
            'price' => '150000.00',
            'duration_minutes' => 60,
        ]);
        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $added->id,
            'price' => '80000.00',
            'duration_minutes' => 40,
        ]);
    }

    public function test_admin_can_mark_an_appointment_as_no_show(): void
    {
        $appointment = $this->createAppointment(Customer::query()->create(['name' => 'Lian']), '2026-10-05', '14:00:00');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->patch(route('appointments.update-status', $appointment), ['status' => 'NO_SHOW'])
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'NO_SHOW']);
    }

    public function test_admin_cannot_reschedule_a_past_appointment_using_the_status_action(): void
    {
        $this->travelTo('2026-10-20 12:00:00');
        $appointment = $this->createAppointment(
            Customer::query()->create(['name' => 'Lian']),
            '2026-10-20',
            '11:00:00',
            'CANCELLED',
        );

        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('appointments'))
            ->patch(route('appointments.update-status', $appointment), ['status' => 'SCHEDULED'])
            ->assertSessionHasErrors([
                'status' => 'Janji temu berstatus terjadwal harus dijadwalkan pada waktu mendatang.',
            ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'CANCELLED',
        ]);
    }

    public function test_inactive_services_cannot_be_added_to_a_new_appointment(): void
    {
        $this->travelTo('2026-10-04 12:00:00');
        $customer = Customer::query()->create(['name' => 'Lian']);
        $inactiveService = Service::query()->create([
            'name' => 'Unavailable service',
            'price' => 100000,
            'duration_minutes' => 45,
            'is_active' => false,
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('appointments'))
            ->post(route('appointments.store'), [
                'customer_id' => $customer->id,
                'appointment_date' => '2026-10-05',
                'start_time' => '14:00',
                'status' => 'SCHEDULED',
                'services' => [['id' => $inactiveService->id]],
            ])
            ->assertSessionHasErrors('services');

        $this->assertDatabaseMissing('appointments', ['customer_id' => $customer->id]);
    }

    public function test_admin_cannot_create_a_scheduled_appointment_in_the_past(): void
    {
        $this->travelTo('2026-10-20 12:00:00');
        $customer = Customer::query()->create(['name' => 'Lian']);
        $service = $this->createService('Gel manicure', 150000, 60);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('appointments'))
            ->post(route('appointments.store'), [
                'customer_id' => $customer->id,
                'appointment_date' => '2026-10-20',
                'start_time' => '11:00',
                'status' => 'SCHEDULED',
                'services' => [['id' => $service->id]],
            ])
            ->assertSessionHasErrors([
                'appointment_date' => 'Janji temu berstatus terjadwal harus dijadwalkan pada waktu mendatang.',
            ]);

        $this->assertDatabaseMissing('appointments', ['customer_id' => $customer->id]);
    }

    public function test_admin_cannot_update_a_scheduled_appointment_to_a_past_time(): void
    {
        $this->travelTo('2026-10-20 12:00:00');
        $customer = Customer::query()->create(['name' => 'Lian']);
        $service = $this->createService('Gel manicure', 150000, 60);
        $appointment = $this->createAppointment($customer, '2026-10-21', '14:00:00');
        $appointment->services()->attach($service->id, [
            'price' => 150000,
            'duration_minutes' => 60,
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('appointments'))
            ->put(route('appointments.update', $appointment), [
                'customer_id' => $customer->id,
                'appointment_date' => '2026-10-20',
                'start_time' => '11:00',
                'status' => 'SCHEDULED',
                'services' => [['id' => $service->id]],
            ])
            ->assertSessionHasErrors('appointment_date');

        $this->assertSame('2026-10-21', $appointment->fresh()->appointment_date->toDateString());
        $this->assertSame('SCHEDULED', $appointment->fresh()->status);
    }

    public function test_overdue_scheduled_appointments_are_cancelled_after_their_service_duration(): void
    {
        $this->travelTo('2026-10-20 11:30:00');
        $customer = Customer::query()->create(['name' => 'Lian']);
        $hourService = $this->createService('One-hour service', 150000, 60);
        $halfHourService = $this->createService('Half-hour service', 50000, 30);
        $overdue = $this->createAppointment($customer, '2026-10-20', '10:00:00');
        $overdue->services()->attach($hourService->id, ['price' => 150000, 'duration_minutes' => 60]);
        $inProgress = $this->createAppointment($customer, '2026-10-20', '11:00:00');
        $inProgress->services()->attach($hourService->id, ['price' => 150000, 'duration_minutes' => 60]);
        $multipleServicesInProgress = $this->createAppointment($customer, '2026-10-20', '10:30:00');
        $multipleServicesInProgress->services()->attach([
            $hourService->id => ['price' => 150000, 'duration_minutes' => 60],
            $halfHourService->id => ['price' => 50000, 'duration_minutes' => 30],
        ]);
        $upcoming = $this->createAppointment($customer, '2026-10-20', '12:30:00');
        $upcoming->services()->attach($halfHourService->id, ['price' => 50000, 'duration_minutes' => 30]);

        $this->artisan('appointments:cancel-overdue')
            ->expectsOutput('1 janji temu terlewat otomatis dibatalkan.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('appointments', ['id' => $overdue->id, 'status' => 'CANCELLED']);
        $this->assertDatabaseHas('appointments', ['id' => $inProgress->id, 'status' => 'SCHEDULED']);
        $this->assertDatabaseHas('appointments', ['id' => $multipleServicesInProgress->id, 'status' => 'SCHEDULED']);
        $this->assertDatabaseHas('appointments', ['id' => $upcoming->id, 'status' => 'SCHEDULED']);
    }

    public function test_admin_can_delete_appointment_and_its_service_lines(): void
    {
        $customer = Customer::query()->create(['name' => 'Lian']);
        $service = $this->createService('Gel manicure', 150000, 60);
        $appointment = $this->createAppointment($customer, '2026-10-05', '14:00:00');
        $appointment->services()->attach($service->id, [
            'price' => 150000,
            'duration_minutes' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->delete(route('appointments.destroy', $appointment))
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'success');

        $this->assertModelMissing($appointment);
        $this->assertDatabaseMissing('appointment_services', ['appointment_id' => $appointment->id]);
    }

    public function test_admin_can_bulk_delete_appointments_and_their_service_lines(): void
    {
        $customer = Customer::query()->create(['name' => 'Lian']);
        $service = $this->createService('Gel manicure', 150000, 60);
        $first = $this->createAppointment($customer, '2026-10-05', '14:00:00');
        $second = $this->createAppointment($customer, '2026-10-06', '15:00:00');
        foreach ([$first, $second] as $appointment) {
            $appointment->services()->attach($service->id, [
                'price' => 150000,
                'duration_minutes' => 60,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($this->demoAdmin(), 'admin')
            ->delete(route('appointments.destroy-selected'), [
                'ids' => [$first->id, $second->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'success')
            ->assertSessionHas('toast.message', '2 janji temu berhasil dihapus.');

        $this->assertModelMissing($first);
        $this->assertModelMissing($second);
        $this->assertDatabaseMissing('appointment_services', ['appointment_id' => $first->id]);
        $this->assertDatabaseMissing('appointment_services', ['appointment_id' => $second->id]);
    }

    public function test_bulk_appointment_deletion_rejects_duplicate_ids(): void
    {
        $appointment = $this->createAppointment(Customer::query()->create(['name' => 'Lian']), '2026-10-05', '14:00:00');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('appointments'))
            ->delete(route('appointments.destroy-selected'), [
                'ids' => [$appointment->id, $appointment->id],
            ])
            ->assertRedirect(route('appointments'))
            ->assertSessionHasErrors('ids.1');

        $this->assertModelExists($appointment);
    }

    public function test_guest_is_redirected_from_appointment_management(): void
    {
        $this->get(route('appointments'))->assertRedirect(route('login'));
    }

    private function demoAdmin(): Admin
    {
        return Admin::query()->where('email', 'admin123@gmail.com')->firstOrFail();
    }

    private function createService(string $name, int $price, int $duration): Service
    {
        return Service::query()->create([
            'name' => $name,
            'price' => $price,
            'duration_minutes' => $duration,
            'is_active' => true,
        ]);
    }

    private function createAppointment(
        Customer $customer,
        string $date,
        string $time,
        string $status = 'SCHEDULED',
    ): Appointment {
        return Appointment::query()->create([
            'customer_id' => $customer->id,
            'appointment_date' => $date,
            'start_time' => $time,
            'status' => $status,
        ]);
    }
}
