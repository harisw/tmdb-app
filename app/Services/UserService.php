<?php

namespace App\Services;

use App\Models\Country;
use App\Models\User;
use App\Utils\Helpers\GenerateUsername;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(private GenerateUsername $usernameGenerator) {}

    public function create(array $data): ?User
    {
        $username = $this->getUniqueUsername();

        return User::create([
            'name' => explode('@', $data['email'])[0],
            'username' => $username,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'country_id' => Country::where('iso2', $data['country'])->first()->id,
            'extra_attributes' => [
                'avatar' => [
                    'seed' => $username,
                    'style' => AvatarService::getRandomStyle(),
                ],
            ],
        ]);
    }

    private function getUniqueUsername(): string
    {
        do {
            $newUsername = ($this->usernameGenerator)();
        } while (User::where('username', $newUsername)->exists());

        return $newUsername;
    }
}
