<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $admin = $this->authenticatedAdmin($request);

        return Inertia::render('Settings/Index', [
            'admin' => [
                'name' => $admin->name,
                'email' => $admin->email,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = $this->authenticatedAdmin($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('admins', 'name')->ignore($admin)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin)],
        ], [
            'email.email' => 'Masukkan alamat email dengan format yang valid.',
        ]);

        $admin->name = $validated['name'];
        $admin->email = $validated['email'];
        $admin->save();

        return redirect()->route('settings')->with('toast', [
            'type' => 'success',
            'message' => 'Nama pengguna dan email berhasil diperbarui.',
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $admin = $this->authenticatedAdmin($request);
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $admin->password = Hash::make($validated['password']);
        $admin->save();

        return redirect()->route('settings')->with('toast', [
            'type' => 'success',
            'message' => 'Kata sandi berhasil diperbarui.',
        ]);
    }

    private function authenticatedAdmin(Request $request): Admin
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
