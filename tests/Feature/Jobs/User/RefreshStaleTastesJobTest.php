<?php

namespace Tests\Feature\Jobs\User;

use App\Jobs\User\RefreshStaleTastesJob;
use App\Models\Title;
use App\Models\User;
use App\Models\UserTaste;
use App\Models\UserTitleState;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RefreshStaleTastesJobTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        $this->user = User::factory()->create();
    }

    private function changeState(Carbon $at, ?User $user = null): void
    {
        UserTitleState::factory()->create([
            'user_id' => ($user ?? $this->user)->id,
            'title_id' => Title::factory()->embedded()->create()->id,
            'liked' => true,
            'updated_at' => $at,
        ]);
    }

    private function computedAt(Carbon $at): void
    {
        $taste = new UserTaste(['user_id' => $this->user->id, 'embedding_model' => 'text-embedding-3-small']);
        $taste->updated_at = $at;
        $taste->save();
    }

    private function refresh(int $limit = 10): void
    {
        (new RefreshStaleTastesJob($limit))->handle(app(UserService::class));
    }

    private function wasRefreshed(?User $user = null): bool
    {
        return UserTaste::find(($user ?? $this->user)->id)?->updated_at->eq(now()) ?? false;
    }

    public function test_a_user_without_states_is_skipped(): void
    {
        $this->refresh();

        $this->assertDatabaseCount('user_tastes', 0);
    }

    public function test_a_never_computed_user_is_refreshed(): void
    {
        $this->changeState(now()->subMinute());

        $this->refresh();

        $this->assertTrue($this->wasRefreshed());
    }

    public function test_a_user_is_refreshed_after_a_change_since_the_last_computation(): void
    {
        $this->computedAt(now()->subHour());
        $this->changeState(now()->subMinute());

        $this->refresh();

        $this->assertTrue($this->wasRefreshed());
    }

    public function test_a_user_without_changes_since_the_last_computation_is_skipped(): void
    {
        $this->changeState(now()->subHour());
        $this->computedAt(now()->subMinutes(10));

        $this->refresh();

        $this->assertFalse($this->wasRefreshed());
    }

    public function test_each_run_takes_the_users_waiting_longest_up_to_the_limit(): void
    {
        $waitingLonger = User::factory()->create();
        $this->changeState(now()->subMinute());
        $this->changeState(now()->subHour(), $waitingLonger);

        $this->refresh(limit: 1);

        $this->assertTrue($this->wasRefreshed($waitingLonger));
        $this->assertFalse($this->wasRefreshed());
    }

    public function test_it_logs_how_many_users_it_refreshes(): void
    {
        Log::spy();
        $this->changeState(now()->subMinute());

        $this->refresh();

        Log::shouldHaveReceived('info')->once()->with('Refreshing tastes for 1 users');
    }
}
