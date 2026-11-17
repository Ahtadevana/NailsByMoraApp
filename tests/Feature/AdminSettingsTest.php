<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_admin_can_view_account_settings(): void
    {
        $admin = $this->demoAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('settings'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('admin.name', 'admin123')
                ->where('admin.email', 'admin123@gmail.com')
                ->missing('admin.password'));
    }

    public function test_admin_can_update_profile_and_password_separately_without_losing_the_current_session(): void
    {
        $admin = $this->demoAdmin();

        $this->actingAs($admin, 'admin')
            ->put(route('settings.update'), [
                'name' => 'studio-owner',
                'email' => 'owner@example.test',
            ])
            ->assertRedirect(route('settings'))
            ->assertSessionHas('toast.message', 'Nama pengguna dan email berhasil diperbarui.');

        $this->put(route('settings.password.update'), [
            'current_password' => 'admin12345',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('settings'))
            ->assertSessionHas('toast.message', 'Kata sandi berhasil diperbarui.');

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertSame('studio-owner', $admin->fresh()->name);
        $this->assertSame('owner@example.test', $admin->fresh()->email);
        $this->assertTrue(Hash::check('new-secure-password', $admin->fresh()->password));

        $this->get(route('settings'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('admin.name', 'studio-owner')
                ->where('admin.email', 'owner@example.test'));
    }

    public function test_admin_can_update_profile_and_keep_the_current_password(): void
    {
        $admin = $this->demoAdmin();

        $this->actingAs($admin, 'admin')
            ->put(route('settings.update'), [
                'name' => 'new-owner',
                'email' => 'admin123@gmail.com',
            ])
            ->assertRedirect(route('settings'));

        $this->assertSame('new-owner', $admin->fresh()->name);
        $this->assertTrue(Hash::check('admin12345', $admin->fresh()->password));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_admin_cannot_update_credentials_with_an_incorrect_current_password(): void
    {
        $admin = $this->demoAdmin();

        $this->actingAs($admin, 'admin')
            ->from(route('settings'))
            ->put(route('settings.password.update'), [
                'current_password' => 'incorrect-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSame('admin123', $admin->fresh()->name);
        $this->assertSame('admin123@gmail.com', $admin->fresh()->email);
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_admin_cannot_use_an_email_already_assigned_to_another_admin(): void
    {
        $admin = $this->demoAdmin();
        $otherAdmin = Admin::query()->create([
            'name' => 'another-owner',
            'email' => 'another@example.test',
            'password' => 'admin123',
        ]);

        $this->actingAs($admin, 'admin')
            ->from(route('settings'))
            ->put(route('settings.update'), [
                'name' => 'admin123',
                'email' => $otherAdmin->email,
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('admin123@gmail.com', $admin->fresh()->email);
    }

    public function test_admin_cannot_update_profile_with_an_invalid_email_format(): void
    {
        $admin = $this->demoAdmin();

        $this->actingAs($admin, 'admin')
            ->from(route('settings'))
            ->put(route('settings.update'), [
                'name' => 'admin123',
                'email' => 'not-an-email',
            ])
            ->assertSessionHasErrors([
                'email' => 'Masukkan alamat email dengan format yang valid.',
            ]);

        $this->assertSame('admin123@gmail.com', $admin->fresh()->email);
    }

    public function test_admin_cannot_use_a_username_already_assigned_to_another_admin(): void
    {
        $admin = $this->demoAdmin();
        Admin::query()->create([
            'name' => 'another-owner',
            'email' => 'another@example.test',
            'password' => 'admin123',
        ]);

        $this->actingAs($admin, 'admin')
            ->from(route('settings'))
            ->put(route('settings.update'), [
                'name' => 'another-owner',
                'email' => 'admin123@gmail.com',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame('admin123', $admin->fresh()->name);
    }

    public function test_guest_is_redirected_to_login_from_settings(): void
    {
        $this->get(route('settings'))
            ->assertRedirect(route('login'));
    }

    private function demoAdmin(): Admin
    {
        return Admin::query()->where('email', 'admin123@gmail.com')->firstOrFail();
    }
}
