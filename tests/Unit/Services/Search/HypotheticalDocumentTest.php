<?php

namespace Tests\Unit\Services\Search;

use App\Services\Search\HypotheticalDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class HypotheticalDocumentTest extends TestCase
{
    public function test_maps_llm_json_output_and_trims_it(): void
    {
        $hypothesis = HypotheticalDocument::fromLlmOutput(json_encode(['title' => ' Oldboy ', 'document' => "Oldboy\nA man is imprisoned for 15 years.\n"]));

        $this->assertSame('Oldboy', $hypothesis->title);
        $this->assertSame("Oldboy\nA man is imprisoned for 15 years.", $hypothesis->document);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function missingTitles(): array
    {
        return [
            'null' => [null],
            'blank' => ['  '],
            'not a string' => [42],
        ];
    }

    #[DataProvider('missingTitles')]
    public function test_treats_a_missing_title_as_no_guess(mixed $title): void
    {
        $this->assertNull(HypotheticalDocument::fromLlmOutput(json_encode(['title' => $title, 'document' => 'A moving castle.']))->title);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidOutputs(): array
    {
        return [
            'truncated json' => ['{"title": "Oldboy", "docu'],
            'plain text' => ['Oldboy'],
            'json string' => ['"Oldboy"'],
            'missing document' => ['{"title": "Oldboy"}'],
            'blank document' => ['{"title": "Oldboy", "document": " "}'],
        ];
    }

    #[DataProvider('invalidOutputs')]
    public function test_rejects_output_without_a_document(string $output): void
    {
        $this->expectException(UnexpectedValueException::class);

        HypotheticalDocument::fromLlmOutput($output);
    }
}
