<?php

namespace Tests\Unit\Enums;

use App\Domain\Enums\TitleAction;
use App\Domain\Enums\TitleEventType;
use PHPUnit\Framework\TestCase;

class TitleActionTest extends TestCase
{
    public function test_each_action_maps_to_an_on_and_off_event(): void
    {
        $this->assertSame(
            [TitleEventType::LIKE, TitleEventType::UNLIKE],
            array_values(TitleAction::LIKE->events()),
        );
        $this->assertSame(
            [TitleEventType::TO_WATCH_ADDED, TitleEventType::TO_WATCH_REMOVED],
            array_values(TitleAction::WATCHLIST->events()),
        );
        $this->assertSame(
            [TitleEventType::WATCHED, TitleEventType::WATCHED_REMOVED],
            array_values(TitleAction::WATCHED->events()),
        );
    }

    public function test_only_like_and_watched_take_a_rating(): void
    {
        $this->assertTrue(TitleAction::LIKE->ratable());
        $this->assertTrue(TitleAction::WATCHED->ratable());
        $this->assertFalse(TitleAction::WATCHLIST->ratable());
    }
}
