<?php

namespace App\Data\DTOs;

use Spatie\LaravelData\Data;

class GoogleUserData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $google_id,
        public readonly string $google_token,
    ) {}
}
