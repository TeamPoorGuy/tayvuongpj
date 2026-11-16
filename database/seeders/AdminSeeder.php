<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        \Illuminate\Database\Eloquent\Model::unguard();

        // 1. Quản trị viên tối cao
        User::firstOrCreate(
            ['email' => 'admin@sportfield.com'],
            [
                'name' => 'Quản trị viên Hệ thống (Super Admin)',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '0901234567',
                'address' => 'Hà Nội, Việt Nam',
                'is_active' => true,
                'trust_score' => 150,
            ]
        );

        // 2. Quản trị viên kiểm duyệt
        User::firstOrCreate(
            ['email' => 'admin2@sportfield.com'],
            [
                'name' => 'Quản trị viên Kiểm duyệt (Mod)',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '0907654321',
                'address' => 'Hà Nội, Việt Nam',
                'is_active' => true,
                'trust_score' => 150,
            ]
        );
    }
}
