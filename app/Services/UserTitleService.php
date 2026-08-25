<?php

namespace App\Services;

use App\Data\DTOs\GoogleUserData;
use App\Data\DTOs\UserTitleEventData;
use App\Models\Country;
use App\Models\TitleInteractionEvent;
use App\Models\User;
use App\Utils\Helpers\GenerateUsername;
use Illuminate\Support\Facades\Hash;

class UserTitleService
{
    public function __construct(private GenerateUsername $usernameGenerator) {}

    public function createEvent(UserTitleEventData $data): ?TitleInteractionEvent
    {
        return User::create([
            ...$this->buildBaseAttributes($this->getUniqueUsername()),
            'name' => explode('@', $data->email)[0],
            'email' => $data->email,
            'password' => Hash::make($data->password),
            'country_id' => Country::where('iso2', $data->country_iso2)->first()->id,
        ]);
    }

    public function createFromGoogle(GoogleUserData $data): User
    {
        return User::create([
            ...$this->buildBaseAttributes($this->getUniqueUsername()),
            'name' => $data->name,
            'email' => $data->email,
            'google_id' => $data->google_id,
            'google_token' => $data->google_token,
            'email_verified_at' => now(),
        ]);
    }

    public function linkGoogleAccount(User $user, GoogleUserData $data): User
    {
        $user->update([
            'google_id' => $data->google_id,
            'google_token' => $data->google_token,
        ]);

        return $user;
    }

    public function findByGoogleId(string $googleId): ?User
    {
        return User::where('google_id', $googleId)->first();
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    private function buildBaseAttributes(string $username): array
    {
        return [
            'username' => $username,
            'extra_attributes' => [
                'avatar' => [
                    'seed' => $username,
                    'style' => AvatarService::getRandomStyle(),
                ],
            ],
        ];
    }

    private function getUniqueUsername(): string
    {
        // The generator draws from a fixed adjective/noun list, so on a fresh
        // database it could otherwise hand a new signup the reserved admin name
        // by chance.
        $reserved = array_filter([config('app.admin_username')]);

        do {
            $newUsername = ($this->usernameGenerator)();
        } while (in_array($newUsername, $reserved, true) || User::where('username', $newUsername)->exists());

        return $newUsername;
    }
}
