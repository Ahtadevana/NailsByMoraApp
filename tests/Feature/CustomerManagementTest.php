<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_page_renders_existing_customers_and_summary(): void
    {
        Customer::query()->create(['name' => 'Example Customer']);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('customers'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Customers/Index')
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Example Customer')
                ->where('summary.total', 1)
                ->where('summary.new', 1));
    }

    public function test_admin_can_create_a_customer(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('customers'))
            ->post(route('customers.store'), [
                'name' => 'Ava Rose',
                'phone' => '08123456789',
                'email' => 'ava@example.test',
                'notes' => 'Prefers short nails.',
            ])
            ->assertRedirect(route('customers'));

        $this->assertDatabaseHas('customers', [
            'name' => 'Ava Rose',
            'phone' => '08123456789',
            'email' => 'ava@example.test',
            'notes' => 'Prefers short nails.',
        ]);
        $this->assertSame('Ava Rose berhasil ditambahkan.', session('toast.message'));
    }

    public function test_admin_can_update_a_customer(): void
    {
        $customer = Customer::query()->create(['name' => 'Ava Rose']);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->put(route('customers.update', $customer), [
                'name' => 'Ava Rose updated',
                'phone' => '08123456789',
                'email' => 'ava@example.test',
                'notes' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Ava Rose updated',
            'email' => 'ava@example.test',
        ]);
    }

    public function test_customer_search_filters_by_name_and_phone(): void
    {
        Customer::query()->create(['name' => 'Ava Rose', 'phone' => '08111111111']);
        Customer::query()->create(['name' => 'Mia Pearl', 'phone' => '08222222222']);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('customers', ['search' => '081111']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Ava Rose'));
    }

    public function test_contact_filter_returns_only_customers_with_the_selected_contact_method(): void
    {
        Customer::query()->create(['name' => 'Phone Customer', 'phone' => '08111111111']);
        Customer::query()->create(['name' => 'Email Customer', 'email' => 'email@example.test']);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('customers', ['contact' => 'has_email', 'sort' => 'name_asc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.contact', 'has_email')
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Email Customer'));
    }

    public function test_rows_per_page_is_limited_to_the_supported_choices(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('customers', ['per_page' => 25]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.per_page', 25)
                ->where('customers.per_page', 25));
    }

    public function test_customer_detail_includes_appointment_history_and_completed_spend(): void
    {
        $customer = Customer::query()->create(['name' => 'Ava Rose']);
        $service = Service::query()->create([
            'name' => 'Gel manicure',
            'price' => 250000,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
        $appointment = Appointment::query()->create([
            'customer_id' => $customer->id,
            'appointment_date' => '2026-10-01',
            'start_time' => '10:00:00',
            'status' => 'COMPLETED',
        ]);
        $appointment->services()->attach($service->id, [
            'price' => 250000,
            'duration_minutes' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->getJson(route('customers.show', $customer))
            ->assertOk()
            ->assertJsonPath('customer.name', 'Ava Rose')
            ->assertJsonPath('summary.appointments', 1)
            ->assertJsonPath('summary.total_spent', 250000)
            ->assertJsonPath('appointments.0.services.0', 'Gel manicure');
    }

    public function test_customer_with_appointment_history_cannot_be_deleted(): void
    {
        $customer = Customer::query()->create(['name' => 'Ava Rose']);
        Appointment::query()->create([
            'customer_id' => $customer->id,
            'appointment_date' => '2026-10-01',
            'start_time' => '10:00:00',
            'status' => 'SCHEDULED',
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'error');

        $this->assertModelExists($customer);
    }

    public function test_admin_can_delete_customer_without_appointments(): void
    {
        $customer = Customer::query()->create(['name' => 'Ava Rose']);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'success');

        $this->assertModelMissing($customer);
    }

    public function test_bulk_delete_keeps_customers_with_appointment_history(): void
    {
        $deletableCustomer = Customer::query()->create(['name' => 'Ava Rose']);
        $customerWithAppointment = Customer::query()->create(['name' => 'Mia Pearl']);
        Appointment::query()->create([
            'customer_id' => $customerWithAppointment->id,
            'appointment_date' => '2026-10-01',
            'start_time' => '10:00:00',
            'status' => 'SCHEDULED',
        ]);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->delete(route('customers.destroy-selected'), [
                'ids' => [$deletableCustomer->id, $customerWithAppointment->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'error');

        $this->assertModelMissing($deletableCustomer);
        $this->assertModelExists($customerWithAppointment);
    }

    public function test_customer_creation_requires_a_name_and_valid_email(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('customers'))
            ->post(route('customers.store'), [
                'name' => '',
                'email' => 'not-an-email',
            ])
            ->assertSessionHasErrors([
                'name',
                'email' => 'Masukkan alamat email dengan format yang valid.',
            ]);
    }

    #[DataProvider('invalidCustomerNames')]
    public function test_customer_creation_rejects_names_with_digits_or_symbols(string $name): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('customers'))
            ->post(route('customers.store'), [
                'name' => $name,
                'email' => 'ava@example.test',
            ])
            ->assertSessionHasErrors([
                'name' => 'Nama hanya boleh berisi huruf dan spasi; angka dan simbol tidak diperbolehkan.',
            ]);

        $this->assertDatabaseMissing('customers', ['name' => $name]);
    }

    public function test_customer_update_rejects_names_with_digits_or_symbols(): void
    {
        $customer = Customer::query()->create(['name' => 'Ava Rose']);

        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('customers'))
            ->put(route('customers.update', $customer), [
                'name' => 'Ava 2',
                'email' => null,
            ])
            ->assertSessionHasErrors([
                'name' => 'Nama hanya boleh berisi huruf dan spasi; angka dan simbol tidak diperbolehkan.',
            ]);

        $this->assertSame('Ava Rose', $customer->fresh()->name);
    }

    public function test_customer_creation_rejects_phone_numbers_with_non_digits(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('customers'))
            ->post(route('customers.store'), [
                'name' => 'Ava Rose',
                'phone' => '+62 812-3456',
            ])
            ->assertSessionHasErrors([
                'phone' => 'Nomor telepon hanya boleh berisi angka.',
            ]);

        $this->assertDatabaseMissing('customers', ['name' => 'Ava Rose']);
    }

    public function test_quick_customer_creation_rejects_phone_numbers_with_non_digits(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->postJson(route('appointments.customers.quick-store'), [
                'name' => 'Ava Rose',
                'phone' => '0812abc',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', 'Nomor telepon hanya boleh berisi angka.');

        $this->assertDatabaseMissing('customers', ['name' => 'Ava Rose']);
    }

    public static function invalidCustomerNames(): array
    {
        return [
            'digits' => ['Ava 2'],
            'symbols' => ['Ava @ Rose'],
        ];
    }

    private function demoAdmin(): Admin
    {
        return Admin::query()->where('email', 'admin123@gmail.com')->firstOrFail();
    }
}
