<?php

namespace App\Utils\Traits;

use App\Models\User;
use App\Utils\Helpers\GenerateUsername;

trait HasAuthMethods
{
    protected function getUniqueUsername(GenerateUsername $generateUsername): string
    {
        do {
            $newUsername = $generateUsername();
        } while (User::where('username', $newUsername)->exists());

        return $newUsername;
    }
}
