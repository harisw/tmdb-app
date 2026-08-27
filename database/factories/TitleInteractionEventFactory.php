<?php

namespace Database\Factories;

use App\Domain\Enums\TitleEventType;
use App\Models\Title;
use App\Models\TitleInteractionEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TitleInteractionEvent>
 */
class TitleInteractionEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventType = fake()->randomElement(TitleEventType::cases());

        return [
            'user_id' => User::factory(),
            'title_id' => Title::factory(),
            'event_type' => $eventType,
            'value' => $eventType === TitleEventType::RATE
                ? fake()->randomFloat(1, 0, 5)
                : null,
        ];
    }
}
