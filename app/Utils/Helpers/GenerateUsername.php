<?php

namespace App\Utils\Helpers;

class GenerateUsername
{
    public function __invoke(): string
    {
        $adjectives = config('usernames.adjectives');
        $characters = config('usernames.characters');

        $adj = $adjectives[array_rand($adjectives)];
        $char = $characters[array_rand($characters)];
        $number = random_int(10, 100);

        return "{$adj}{$char}{$number}";
    }
}
