<?php

namespace Database\Factories;

use App\Models\Title;
use App\Models\User;
use App\Models\UserTitleState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserTitleState>
 */
class UserTitleStateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title_id' => Title::factory(),
            'rate' => 0,
            'watched' => false,
            'to_watch' => false,
        ];
    }
}
