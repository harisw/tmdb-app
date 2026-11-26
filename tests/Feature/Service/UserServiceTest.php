<?php

namespace Tests\Feature\Service;

use App\Models\Title;
use App\Models\User;
use App\Models\UserTaste;
use App\Models\UserTitleState;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UserService::class);
        $this->user = User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function interact(Title $title, array $state): void
    {
        UserTitleState::factory()->create([
            'user_id' => $this->user->id,
            'title_id' => $title->id,
            'rate' => null,
            ...$state,
        ]);
    }

    /**
     * @return list<float>
     */
    private function storedTaste(): array
    {
        return array_slice(json_decode(UserTaste::findOrFail($this->user->id)->taste, true), 0, 5);
    }

    public function test_it_averages_the_embeddings_of_liked_titles(): void
    {
        $this->interact(Title::factory()->embedded([0 => 1.0])->create(), ['liked' => true]);
        $this->interact(Title::factory()->embedded([1 => 1.0])->create(), ['liked' => true]);

        $taste = $this->service->computeUserTaste($this->user);

        $this->assertSame($this->user->id, $taste->user_id);
        $this->assertSame('text-embedding-3-small', $taste->embedding_model);
        $this->assertEqualsWithDelta([0.5, 0.5, 0.0, 0.0, 0.0], $this->storedTaste(), 1e-6);
    }

    public function test_it_weights_each_title_by_its_strongest_signal(): void
    {
        $this->interact(Title::factory()->embedded([0 => 1.0])->create(), ['liked' => true, 'rate' => 2]);
        $this->interact(Title::factory()->embedded([1 => 1.0])->create(), ['watched' => true, 'rate' => 8]);
        $this->interact(Title::factory()->embedded([2 => 1.0])->create(), ['watched' => true]);
        $this->interact(Title::factory()->embedded([3 => 1.0])->create(), ['to_watch' => true]);
        $this->interact(Title::factory()->embedded([4 => 1.0])->create(), ['watched' => true, 'rate' => 4]);

        $this->service->computeUserTaste($this->user);

        $total = 1.0 + 0.8 + 0.5 + 0.3;
        $this->assertEqualsWithDelta([1.0 / $total, 0.8 / $total, 0.5 / $total, 0.3 / $total, 0.0], $this->storedTaste(), 1e-6);
    }

    public function test_it_ignores_titles_without_an_embedding(): void
    {
        $this->interact(Title::factory()->embedded([0 => 1.0])->create(), ['liked' => true]);
        $this->interact(Title::factory()->embedded([0 => 1.0])->create(), ['liked' => true]);
        $this->interact(Title::factory()->create(), ['liked' => true]);

        $this->service->computeUserTaste($this->user);

        $this->assertEqualsWithDelta([1.0, 0.0, 0.0, 0.0, 0.0], $this->storedTaste(), 1e-6);
    }

    public function test_it_replaces_the_existing_taste_instead_of_adding_a_row(): void
    {
        $this->interact(Title::factory()->embedded([0 => 1.0])->create(), ['liked' => true]);
        $this->interact(Title::factory()->embedded([1 => 1.0])->create(), ['liked' => true]);
        $this->service->computeUserTaste($this->user);

        $this->interact(Title::factory()->embedded([2 => 1.0])->create(), ['liked' => true]);
        $this->service->computeUserTaste($this->user);

        $this->assertDatabaseCount('user_tastes', 1);
        $this->assertEqualsWithDelta([1 / 3, 1 / 3, 1 / 3, 0.0, 0.0], $this->storedTaste(), 1e-6);
    }

    public function test_it_needs_at_least_two_likes_worth_of_signal(): void
    {
        $this->interact(Title::factory()->embedded()->create(), ['liked' => true]);
        $this->interact(Title::factory()->embedded()->create(), ['to_watch' => true]);

        $this->assertNull($this->service->computeUserTaste($this->user)->taste);
        $this->assertDatabaseHas('user_tastes', ['user_id' => $this->user->id, 'taste' => null]);
    }

    public function test_it_clears_the_taste_once_the_signal_drops_below_the_minimum(): void
    {
        $kept = Title::factory()->embedded()->create();
        $unliked = Title::factory()->embedded()->create();
        $this->interact($kept, ['liked' => true]);
        $this->interact($unliked, ['liked' => true]);
        $this->service->computeUserTaste($this->user);

        UserTitleState::where('user_id', $this->user->id)->where('title_id', $unliked->id)->update(['liked' => false]);

        $this->assertNull($this->service->computeUserTaste($this->user)->taste);
        $this->assertDatabaseHas('user_tastes', ['user_id' => $this->user->id, 'taste' => null]);
        $this->assertDatabaseCount('user_tastes', 1);
    }

    public function test_it_records_the_computation_for_a_user_without_interactions(): void
    {
        $this->assertNull($this->service->computeUserTaste($this->user)->taste);
        $this->assertDatabaseHas('user_tastes', ['user_id' => $this->user->id, 'taste' => null]);
    }

    public function test_it_stamps_the_computation_even_when_the_taste_is_unchanged(): void
    {
        $this->interact(Title::factory()->embedded()->create(), ['liked' => true]);
        $this->interact(Title::factory()->embedded()->create(), ['liked' => true]);
        $first = $this->service->computeUserTaste($this->user)->updated_at;

        $this->travel(5)->minutes();

        $this->assertTrue($this->service->computeUserTaste($this->user)->updated_at->gt($first));
    }
}
