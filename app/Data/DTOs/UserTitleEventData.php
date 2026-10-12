<?php

namespace App\Data\DTOs;

use App\Models\User;
use Spatie\LaravelData\Data;

class UserTitleEventData extends Data
{
    private ?User $user = null;

    public function __construct(
        public readonly int $title_id,
        public readonly string $type,
        public readonly ?float $value,
    ) {}

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}
