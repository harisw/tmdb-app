<?php

namespace App\Data\DTOs;

use App\Models\User;
use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $username,
        public readonly string $email,
        public readonly ?string $avatar_url,
        public readonly ?string $avatar_style,
        public readonly ?string $country,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            username: $user->username,
            email: $user->email,
            avatar_url: $user->avatar_url,
            avatar_style: $user->extra_attributes->get('avatar.style'),
            country: $user->country?->iso2,
        );
    }
}
