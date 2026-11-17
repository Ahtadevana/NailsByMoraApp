<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_the_mobile_admin_sign_in_form(): void
    {
        $this->get('/login')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('errors', []));
    }

    public function test_testing_admin_can_sign_in_with_username_and_reach_the_main_dashboard(): void
    {
        $this->post('/login', [
            'username' => 'admin123',
            'password' => 'admin12345',
            'remember' => true,
        ])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('auth.admin.name', 'admin123'));
    }

    public function test_testing_admin_can_sign_in_with_email(): void
    {
        $this->post('/login', [
            'username' => 'admin123@gmail.com',
            'password' => 'admin12345',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_only_the_requested_testing_admin_is_seeded(): void
    {
        $admin = Admin::query()->where('email', 'admin123@gmail.com')->firstOrFail();

        $this->assertDatabaseCount('admins', 1);
        $this->assertSame('admin123', $admin->name);
        $this->assertTrue(Hash::check('admin12345', $admin->password));
    }

    public function test_invalid_admin_credentials_return_a_login_error(): void
    {
        $this->from('/login')->post('/login', [
            'username' => 'admin123@gmail.com',
            'password' => 'incorrect-password',
        ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'username' => 'Username/email atau kata sandi salah.',
            ]);
    }

    public function test_guest_is_redirected_to_login_from_the_dashboard(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_admin_can_open_every_sidebar_page(): void
    {
        $admin = Admin::query()->where('email', 'admin123@gmail.com')->firstOrFail();
        $this->actingAs($admin, 'admin');

        foreach (['dashboard', 'customers', 'services', 'appointments', 'inventory', 'expenses', 'incomes', 'reports', 'settings'] as $page) {
            $this->get(route($page))
                ->assertInertia(fn (Assert $inertiaPage) => $inertiaPage
                    ->component(match ($page) {
                        'dashboard' => 'Dashboard/Index',
                        'customers' => 'Customers/Index',
                        'services' => 'Services/Index',
                        'appointments' => 'Appointments/Index',
                        'inventory' => 'Inventory/Index',
                        'expenses' => 'Expenses/Index',
                        'incomes' => 'Incomes/Index',
                        'reports' => 'Reports/Index',
                        'settings' => 'Settings/Index',
                        default => 'Empty',
                    })
                    ->where('auth.admin.name', 'admin123'));
        }
    }

    public function test_admin_can_log_out(): void
    {
        $admin = Admin::query()->where('email', 'admin123@gmail.com')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest('admin');
    }
}
