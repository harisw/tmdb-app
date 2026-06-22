<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Horizon User
        User::firstOrCreate([
            'name' => 'Horizon Admin',
            'email' => 'horizonadmin@mail.com',
            'username' => 'horizonAdmin',
            'password' => bcrypt(env('HORIZON_ADMIN_PASSWORD')),
            'email_verified_at' => now(),
        ]);
    }
}
