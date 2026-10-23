<?php

namespace App\Services\Search;

use UnexpectedValueException;

readonly class HypotheticalDocument
{
    public function __construct(
        public ?string $title,
        public string $document,
    ) {}

    public static function fromLlmOutput(string $output): self
    {
        $entry = json_decode($output, true);

        throw_unless(
            is_string($entry['document'] ?? null) && filled($entry['document']),
            UnexpectedValueException::class,
            "LLM output is not a hypothetical document: {$output}",
        );

        return new self(
            title: is_string($entry['title'] ?? null) && filled($entry['title']) ? trim($entry['title']) : null,
            document: trim($entry['document']),
        );
    }
}
