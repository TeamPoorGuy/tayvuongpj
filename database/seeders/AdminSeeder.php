<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@sportfield.com'],
            [
                'name' => 'Quản trị viên Hệ thống',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '0901234567',
                'address' => 'Hà Nội, Việt Nam',
                'is_active' => true,
            ]
        );
    }
}
