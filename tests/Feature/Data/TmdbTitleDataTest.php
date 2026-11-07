<?php

namespace Tests\Feature\Data;

use App\Data\DTOs\TmdbApi\TmdbTitleData;
use App\Domain\Enums\TitleType;
use Tests\TestCase;

class TmdbTitleDataTest extends TestCase
{
    private function payload(array $overrides): array
    {
        return [
            'id' => 1396,
            'vote_average' => 8.9,
            'status' => 'Ended',
            'adult' => false,
            'overview' => 'A chemistry teacher turns to crime.',
            'popularity' => 120.5,
            'language_id' => 1,
            'genres' => [['name' => 'Drama']],
            'production_companies' => [],
            ...$overrides,
        ];
    }

    public function test_maps_movie_title_and_release_date(): void
    {
        $data = TmdbTitleData::from($this->payload([
            'type' => TitleType::MOVIE,
            'title' => 'Heat',
            'release_date' => '1995-12-15',
        ]));

        $this->assertSame('Heat', $data->name);
        $this->assertSame('1995-12-15', $data->release_date);
    }

    public function test_maps_tv_name_and_first_air_date(): void
    {
        $data = TmdbTitleData::from($this->payload([
            'type' => TitleType::TV,
            'name' => 'Breaking Bad',
            'first_air_date' => '2008-01-20',
        ]));

        $this->assertSame('Breaking Bad', $data->name);
        $this->assertSame('breaking-bad', $data->slug);
        $this->assertSame('2008-01-20', $data->release_date);
    }

    public function test_keeps_a_missing_first_air_date_null(): void
    {
        $data = TmdbTitleData::from($this->payload([
            'type' => TitleType::TV,
            'name' => 'Unannounced Show',
        ]));

        $this->assertNull($data->release_date);
    }
}
