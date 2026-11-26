<?php

namespace Tests\Feature\Jobs\User;

use App\Jobs\User\ComputeUserTasteJob;
use App\Models\Title;
use App\Models\User;
use App\Models\UserTitleState;
use Illuminate\Bus\DebounceLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComputeUserTasteJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_computes_the_users_taste(): void
    {
        $user = User::factory()->create();
        foreach (Title::factory(2)->embedded()->create() as $title) {
            UserTitleState::factory()->create(['user_id' => $user->id, 'title_id' => $title->id, 'liked' => true]);
        }

        ComputeUserTasteJob::dispatch($user->id);

        $this->assertDatabaseHas('user_tastes', ['user_id' => $user->id]);
    }

    public function test_it_does_nothing_for_a_deleted_user(): void
    {
        ComputeUserTasteJob::dispatch(999999);

        $this->assertDatabaseCount('user_tastes', 0);
    }

    public function test_each_user_is_debounced_separately(): void
    {
        $this->assertNotSame(
            DebounceLock::getKey(new ComputeUserTasteJob(1)),
            DebounceLock::getKey(new ComputeUserTasteJob(2)),
        );
    }
}
