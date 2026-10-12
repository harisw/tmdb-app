<?php

namespace Tests\Unit\Enums;

use App\Domain\Enums\StreamQuality;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StreamQualityTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string}>
     */
    public static function qualities(): array
    {
        return [
            'lowercase sd' => ['sd', 'SD'],
            'lowercase hd' => ['hd', 'HD'],
            'uhd' => ['uhd', '4k'],
            'uppercase 4K' => ['4K', '4k'],
            'empty' => ['', null],
            'unknown' => ['cam-rip', null],
        ];
    }

    #[DataProvider('qualities')]
    public function test_maps_api_quality_strings(string $input, ?string $expected): void
    {
        $this->assertSame($expected, StreamQuality::mapString($input));
    }
}
