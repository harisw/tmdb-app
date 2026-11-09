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
        ], [
            'name' => 'Admin',
            'email' => 'admin@mail.com',
            'username' => config('app.admin_username'),
            'password' => bcrypt(config('app.admin_password')),
            'email_verified_at' => now(),
            'extra_attributes' => [
                'avatar' => [
                    'seed' => config('app.admin_username'),
                    'style' => AvatarService::getRandomStyle(),
                ],
            ],
        ]);
    }
}
