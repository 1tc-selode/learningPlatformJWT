<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user only if it doesn't exist
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
            ]
        );

        // Create test student user only if it doesn't exist
        User::firstOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'Test Student',
                'password' => Hash::make('student123'),
                'role' => 'student',
            ]
        );

        // Create additional random student users only if we have less than 10 total users
        $existingUsersCount = User::count();
        $usersToCreate = max(0, 10 - $existingUsersCount);
        
        if ($usersToCreate > 0) {
            User::factory($usersToCreate)->create([
                'role' => 'student'
            ]);
        }
    }
}
