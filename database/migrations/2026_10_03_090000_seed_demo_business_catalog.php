<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('admins')->updateOrInsert(
            ['email' => 'admin123@gmail.com'],
            [
                'name' => 'admin123',
                'password' => Hash::make('admin12345'),
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('admins')->where('email', 'admin123@gmail.com')->delete();
    }
};
