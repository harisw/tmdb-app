<?php

namespace App\Data\DTOs;

use Spatie\LaravelData\Data;

class RegisterUserData extends Data
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $country_iso2,
    ) {}
}
