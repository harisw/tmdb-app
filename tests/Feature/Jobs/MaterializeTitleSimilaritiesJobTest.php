<?php

namespace Tests\Feature\Jobs;

use App\Jobs\MaterializeTitleSimilaritiesJob;
use App\Models\Genre;
use App\Models\Title;
use App\Models\TitleSimilarity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MaterializeTitleSimilaritiesJobTest extends TestCase
{
    use RefreshDatabase;

    private const DIMENSIONS = 1536;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.knowledge_graph.min_score' => 0.5]);
    }

    /**
     * @param  array<int, float>  $weights
     */
    private function embeddedTitle(array $weights, array $attributes = []): Title
    {
        $title = Title::factory()->create($attributes);
        $vector = array_replace(array_fill(0, self::DIMENSIONS, 0.0), $weights);
        $title->updateQuietly(['embedding' => '['.implode(',', $vector).']']);

        return $title;
    }

    private function materialize(Title $title): void
    {
        app()->call([new MaterializeTitleSimilaritiesJob($title->id), 'handle']);
    }

    /**
     * @return array<int, int>
     */
    private function storedNeighbourIds(Title $title): array
    {
        return TitleSimilarity::where('title_id', $title->id)->pluck('similar_title_id')->all();
    }

    public function test_stores_neighbours_above_the_minimum_score(): void
    {
        $anchor = $this->embeddedTitle([0 => 1.0]);
        $near = $this->embeddedTitle([0 => 1.0, 1 => 0.2]);
        $this->embeddedTitle([1 => 1.0]);

        $this->materialize($anchor);

        $this->assertSame([$near->id], $this->storedNeighbourIds($anchor));
        $this->assertSame([], $this->storedNeighbourIds($near));
    }

    public function test_stores_reasons_in_the_shape_the_frontend_reads(): void
    {
        $genre = Genre::factory()->create(['name' => 'Drama']);
        $anchor = $this->embeddedTitle([0 => 1.0]);
        $near = $this->embeddedTitle([0 => 1.0, 1 => 0.2]);
        $anchor->genres()->attach($genre);
        $near->genres()->attach($genre);

        $this->materialize($anchor);

        $reasons = TitleSimilarity::where('title_id', $anchor->id)->sole()->reasons;
        $this->assertSame(['semantic_similarity', 'shared_genres'], array_column($reasons, 'type'));
        $this->assertSame(['Drama'], $reasons[1]['values']);
        $this->assertEqualsWithDelta(0.2, $reasons[1]['weight'], 0.0001);
    }

    public function test_replaces_neighbours_that_are_no_longer_similar(): void
    {
        $anchor = $this->embeddedTitle([0 => 1.0]);
        $near = $this->embeddedTitle([0 => 1.0, 1 => 0.2]);
        $far = $this->embeddedTitle([1 => 1.0]);
        $anchor->similarTitles()->attach($far->id, ['score' => 0.9, 'reasons' => []]);

        $this->materialize($anchor);

        $this->assertSame([$near->id], $this->storedNeighbourIds($anchor));
    }

    public function test_records_when_the_title_was_materialized(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $anchor = $this->embeddedTitle([0 => 1.0]);

        $this->materialize($anchor);

        $this->assertEquals(now(), Title::withoutGlobalScopes()->find($anchor->id)->similarities_materialized_at);
    }

    public function test_queues_a_title_only_once_while_it_is_pending(): void
    {
        Queue::fake();

        MaterializeTitleSimilaritiesJob::dispatch(1);
        MaterializeTitleSimilaritiesJob::dispatch(1);
        MaterializeTitleSimilaritiesJob::dispatch(2);

        Queue::assertPushed(MaterializeTitleSimilaritiesJob::class, 2);
    }
}
