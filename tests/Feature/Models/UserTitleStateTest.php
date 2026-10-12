<?php

namespace Tests\Feature\Models;

use App\Models\Title;
use App\Models\User;
use App\Models\UserTitleState;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTitleStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_relates_to_user(): void
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();

        $state = UserTitleState::factory()->create([
            'user_id' => $user->id,
            'title_id' => $title->id,
            'rate' => 3.5,
        ]);

        $this->assertSame('3.5', (string) $state->rate);
        $this->assertTrue($state->user->is($user));
        $this->assertTrue($state->title->is($title));
    }

    public function test_upsert_updates_existing_state_by_composite_key(): void
    {
        $state = UserTitleState::factory()->create(['rate' => 1.0, 'to_watch' => false]);

        UserTitleState::upsert(
            [[
                'user_id' => $state->user_id,
                'title_id' => $state->title_id,
                'rate' => 2.5,
                'to_watch' => true,
            ]],
            ['user_id', 'title_id'],
            ['rate', 'to_watch']
        );

        $fresh = UserTitleState::where('user_id', $state->user_id)
            ->where('title_id', $state->title_id)
            ->first();

        $this->assertSame('2.5', (string) $fresh->rate);
        $this->assertTrue($fresh->to_watch);
        $this->assertSame(1, UserTitleState::where('user_id', $state->user_id)->where('title_id', $state->title_id)->count());
    }

    public function test_rate_above_five_violates_the_valid_rate_check_constraint(): void
    {
        $this->expectException(QueryException::class);

        UserTitleState::factory()->create(['rate' => 10.1]);
    }
}
