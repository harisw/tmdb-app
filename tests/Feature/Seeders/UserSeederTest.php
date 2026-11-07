<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_admin_from_configured_credentials(): void
    {
        config(['app.admin_username' => 'configured-admin', 'app.admin_password' => 'configured-secret']);

        $this->seed(UserSeeder::class);

        $admin = User::where('email', 'admin@mail.com')->firstOrFail();
        $this->assertSame('configured-admin', $admin->username);
        $this->assertTrue(Hash::check('configured-secret', $admin->password));
        $this->assertSame('configured-admin', $admin->extra_attributes['avatar']['seed']);
    }
}
