<?php

namespace Tests\Unit\Data;

use App\Data\DTOs\TitleProviderData;
use App\Domain\Enums\StreamType;
use JsonException;
use PHPUnit\Framework\TestCase;

class TitleProviderDataTest extends TestCase
{
    private function provider(?string $quality): TitleProviderData
    {
        return new TitleProviderData(
            type: StreamType::FLATRATE,
            country_id: 1,
            provider_id: 2,
            country_provider_id: 3,
            title_id: 4,
            link: 'https://example.test/watch',
            quality: $quality,
        );
    }

    public function test_upsert_row_encodes_the_quality(): void
    {
        $this->assertSame('{"quality":"hd"}', $this->provider('hd')->toUpsertArray()['extra_attributes']);
    }

    public function test_upsert_row_refuses_a_quality_that_is_not_valid_utf8(): void
    {
        $this->expectException(JsonException::class);

        $this->provider("\xB1\x31")->toUpsertArray();
    }
}
