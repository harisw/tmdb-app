<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\AvatarService;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin User
        User::firstOrCreate([
            'email' => 'admin@mail.com',
            'username' => env('ADMIN_USERNAME'),
        ], [
            'name' => 'Admin',
            'email' => 'admin@mail.com',
            'username' => env('ADMIN_USERNAME'),
            'password' => bcrypt(env('ADMIN_PASSWORD')),
            'email_verified_at' => now(),
            'extra_attributes' => [
                'avatar' => [
                    'seed' => env('ADMIN_USERNAME'),
                    'style' => AvatarService::getRandomStyle(),
                ],
            ],
        ]);
    }
}
