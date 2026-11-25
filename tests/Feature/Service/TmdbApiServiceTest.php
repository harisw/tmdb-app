<?php

namespace Tests\Feature\Service;

use App\Domain\Enums\TitleType;
use App\Services\TmdbApiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class TmdbApiServiceTest extends TestCase
{
    private function tmdb(): TmdbApiService
    {
        return new TmdbApiService(apiKey: 'test-key', baseUrl: 'https://tmdb.test');
    }

    public function test_returns_the_decoded_payload(): void
    {
        Http::fake(['tmdb.test/3/movie/550/keywords' => Http::response(['id' => 550, 'keywords' => [['id' => 1, 'name' => 'fight']]])]);

        $this->assertSame(
            ['id' => 550, 'keywords' => [['id' => 1, 'name' => 'fight']]],
            $this->tmdb()->getKeywords(TitleType::MOVIE, 550),
        );
    }

    public function test_returns_null_when_tmdb_has_no_such_title(): void
    {
        Http::fake(['tmdb.test/*' => Http::response(['success' => false, 'status_code' => 34], 404)]);

        $this->assertNull($this->tmdb()->getKeywords(TitleType::MOVIE, 999));
    }

    public function test_throws_when_the_request_fails(): void
    {
        Sleep::fake();
        Http::fake(['tmdb.test/*' => Http::response(['success' => false, 'status_code' => 43], 503)]);

        $this->expectException(RequestException::class);

        try {
            $this->tmdb()->getKeywords(TitleType::MOVIE, 550);
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_retries_a_dropped_connection_and_returns_the_payload(): void
    {
        Sleep::fake();
        Http::fake(['tmdb.test/*' => Http::sequence()
            ->pushFailedConnection()
            ->push(['id' => 550, 'keywords' => []])]);

        $this->assertSame(['id' => 550, 'keywords' => []], $this->tmdb()->getKeywords(TitleType::MOVIE, 550));
        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    }

    public function test_throws_the_connection_error_once_retries_run_out(): void
    {
        Sleep::fake();
        Http::fake(['tmdb.test/*' => Http::failedConnection()]);

        $this->expectException(ConnectionException::class);

        try {
            $this->tmdb()->getKeywords(TitleType::MOVIE, 550);
        } finally {
            Http::assertSentCount(3);
        }
    }
}
