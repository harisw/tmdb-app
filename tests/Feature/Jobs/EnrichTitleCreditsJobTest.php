<?php

namespace Tests\Feature\Jobs;

use App\Domain\Enums\TitleType;
use App\Jobs\EnrichTitleCreditsJob;
use App\Models\Title;
use App\Services\TmdbApiService;
use App\Services\TmdbCredits\TmdbCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class EnrichTitleCreditsJobTest extends TestCase
{
    use RefreshDatabase;

    private function runJob(Title $title): void
    {
        (new EnrichTitleCreditsJob($title->id))->handle(
            app(TmdbApiService::class),
            app(TmdbCreditService::class),
        );
    }

    public function test_imports_credits_and_stamps_the_title(): void
    {
        $title = Title::factory()->create([
            'type' => TitleType::MOVIE->value,
            'tmdb_id' => 550,
            'credits_filled_at' => null,
        ]);

        $this->mock(TmdbApiService::class)
            ->shouldReceive('getCredits')
            ->once()
            ->andReturn([
                'cast' => [[
                    'id' => 1, 'name' => 'Edward Norton', 'gender' => 2, 'adult' => false,
                    'popularity' => 20.0, 'profile_path' => '/e.jpg',
                    'character' => 'The Narrator', 'order' => 0, 'credit_id' => 'c1',
                ]],
                'crew' => [],
            ]);

        $this->runJob($title);

        $this->assertDatabaseHas('title_person', [
            'title_id' => $title->id,
            'character' => 'The Narrator',
        ]);
        $this->assertNotNull($title->fresh()->credits_filled_at);
    }

    public function test_stamps_the_title_even_when_tmdb_returns_no_credits(): void
    {
        // Otherwise a title TMDB has nothing for is re-queued on every run.
        $title = Title::factory()->create(['tmdb_id' => 550, 'credits_filled_at' => null]);

        $this->mock(TmdbApiService::class)
            ->shouldReceive('getCredits')
            ->andReturn(['cast' => [], 'crew' => []]);

        $this->runJob($title);

        $this->assertDatabaseCount('title_person', 0);
        $this->assertNotNull($title->fresh()->credits_filled_at);
    }

    public function test_leaves_the_title_unstamped_when_tmdb_fails_so_the_job_retries(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 550, 'credits_filled_at' => null]);

        $this->mock(TmdbApiService::class)
            ->shouldReceive('getCredits')
            ->andThrow(new RuntimeException('TMDB unavailable'));

        $this->expectException(RuntimeException::class);

        try {
            $this->runJob($title);
        } finally {
            $this->assertNull($title->fresh()->credits_filled_at);
        }
    }
}
