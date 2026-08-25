<?php

namespace Tests\Feature\Models;

use App\Domain\Enums\InteractionEventType;
use App\Models\Title;
use App\Models\TitleInteractionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TitleInteractionEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_casts_event_type_to_enum_and_relates_to_user_and_title(): void
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();

        $event = TitleInteractionEvent::factory()->create([
            'user_id' => $user->id,
            'title_id' => $title->id,
            'event_type' => InteractionEventType::RATE,
            'value' => 4.567,
        ]);

        $this->assertSame(InteractionEventType::RATE, $event->event_type);
        $this->assertSame('4.6', (string) $event->value);
        $this->assertTrue($event->user->is($user));
        $this->assertTrue($event->title->is($title));
    }

    public function test_event_has_no_updated_at_column(): void
    {
        $event = TitleInteractionEvent::factory()->create();

        $this->assertArrayNotHasKey('updated_at', $event->getAttributes());
    }

    public function test_non_rate_events_can_omit_value(): void
    {
        $event = TitleInteractionEvent::factory()->create([
            'event_type' => InteractionEventType::WATCHED,
            'value' => null,
        ]);

        $this->assertNull($event->value);
    }
}
