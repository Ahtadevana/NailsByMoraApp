<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_page_renders_services_and_summary(): void
    {
        $this->createService('Example service');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('services'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Services/Index')
                ->has('services.data', 1)
                ->where('services.data.0.name', 'Example service')
                ->where('summary.total', 1)
                ->where('summary.active', 1)
                ->where('summary.average_price', 100000));
    }

    public function test_admin_can_create_and_update_a_service(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->post(route('services.store'), [
                'name' => 'Classic manicure',
                'description' => 'Clean and shape nails.',
                'price' => '125000.00',
                'duration_minutes' => 45,
                'is_active' => true,
            ])
            ->assertRedirect();

        $service = Service::query()->where('name', 'Classic manicure')->firstOrFail();
        $this->assertSame('Classic manicure berhasil ditambahkan.', session('toast.message'));

        $this->put(route('services.update', $service), [
            'name' => 'Classic manicure and polish',
            'description' => null,
            'price' => '150000.00',
            'duration_minutes' => 60,
            'is_active' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Classic manicure and polish',
            'price' => '150000.00',
            'duration_minutes' => 60,
            'is_active' => false,
        ]);
    }

    public function test_search_status_filter_sort_and_page_size_are_applied(): void
    {
        Service::query()->create([
            'name' => 'Acrylic set',
            'description' => 'Extension service',
            'price' => 400000,
            'duration_minutes' => 90,
            'is_active' => false,
        ]);
        Service::query()->create([
            'name' => 'Classic manicure',
            'price' => 100000,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
        $this->createService('Manicure service');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('services', [
                'search' => 'manicure',
                'status' => 'active',
                'sort' => 'price_asc',
                'per_page' => 25,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'active')
                ->where('filters.sort', 'price_asc')
                ->where('filters.per_page', 25)
                ->has('services.data', 2)
                ->where('services.data.0.name', 'Classic manicure')
                ->where('services.data.1.name', 'Manicure service'));
    }

    public function test_admin_can_delete_unreferenced_service(): void
    {
        $service = $this->createService('Temporary service');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->delete(route('services.destroy', $service))
            ->assertRedirect();

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
        $this->assertSame('Layanan berhasil dihapus.', session('toast.message'));
    }

    public function test_service_used_in_appointment_history_is_retained(): void
    {
        $service = $this->createService('Historical service');
        $customer = Customer::query()->create(['name' => 'Historical customer']);
        $appointment = Appointment::query()->create([
            'customer_id' => $customer->id,
            'appointment_date' => '2026-10-02',
            'start_time' => '11:00:00',
            'status' => 'COMPLETED',
        ]);
        $appointment->services()->attach($service->id, [
            'price' => $service->price,
            'duration_minutes' => $service->duration_minutes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->delete(route('services.destroy', $service))
            ->assertRedirect();

        $this->assertDatabaseHas('services', ['id' => $service->id]);
        $this->assertSame('Layanan dalam riwayat janji temu tidak dapat dihapus.', session('toast.message'));
    }

    public function test_bulk_delete_keeps_services_with_appointment_history(): void
    {
        $referenced = $this->createService('Referenced service');
        $unreferenced = $this->createService('Unreferenced service');
        $customer = Customer::query()->create(['name' => 'Historical customer']);
        $appointment = Appointment::query()->create([
            'customer_id' => $customer->id,
            'appointment_date' => '2026-10-02',
            'start_time' => '11:00:00',
            'status' => 'COMPLETED',
        ]);
        $appointment->services()->attach($referenced->id, [
            'price' => $referenced->price,
            'duration_minutes' => $referenced->duration_minutes,
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->delete(route('services.destroy-selected'), [
                'ids' => [$referenced->id, $unreferenced->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('services', ['id' => $referenced->id]);
        $this->assertDatabaseMissing('services', ['id' => $unreferenced->id]);
        $this->assertSame('1 layanan dihapus. Layanan dalam riwayat janji temu tetap disimpan.', session('toast.message'));
    }

    public function test_invalid_service_values_are_rejected(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('services'))
            ->post(route('services.store'), [
                'name' => '',
                'price' => -1,
                'duration_minutes' => 0,
                'is_active' => 'invalid',
            ])
            ->assertRedirect(route('services'))
            ->assertSessionHasErrors(['name', 'price', 'duration_minutes', 'is_active']);
    }

    private function demoAdmin(): Admin
    {
        return Admin::query()->where('email', 'admin123@gmail.com')->firstOrFail();
    }

    private function createService(string $name): Service
    {
        return Service::query()->create([
            'name' => $name,
            'price' => 100000,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);
    }
}
