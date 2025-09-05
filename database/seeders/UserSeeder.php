<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->create([
            'email' => 'johndow@gmail.com',
            'mobile' => '09151234567',
            'password' => Hash::make('johndow1234567'),
            'role' => 'customer',
        ]);
        User::query()->create([
            'email' => 'ali@gmail.com',
            'mobile' => '09151234564',
            'password' => Hash::make('ali1234567'),
            'role' => 'superadmin',
        ]);
        User::query()->create([
            'email' => 'gholam@gmail.com',
            'mobile' => '09151234560',
            'password' => Hash::make('gholam1234567'),
            'role' => 'admin',
        ]);
    }
}
