<?php

namespace App\Services\Search;

use App\Services\Llm\LlmGateway;
use Illuminate\Support\Facades\Cache;

class HypotheticalDocumentGenerator
{
    private const PROMPT_VERSION = 1;

    private const INSTRUCTIONS = <<<'PROMPT'
        You help a movie and TV search engine. The user's message is a search query in any language.
        Work out which film or series they most likely mean and write its catalogue entry in English, in exactly this format:

        <Title>: <tagline>
        <Two or three sentence plot overview>
        Genres: <comma-separated genres>
        Themes: <comma-separated themes and keywords>
        Directed by <director>.
        Written by <writers>.
        Starring <up to five lead actors>.

        If no specific title fits, write an entry for a plausible one but leave out the title, director, writer and cast lines.
        Reply with the entry only.
        PROMPT;

    public function __construct(private readonly LlmGateway $gateway) {}

    public function generate(string $query): string
    {
        $normalized = mb_strtolower(trim($query));

        return Cache::remember(
            'hyde-document:'.self::PROMPT_VERSION.':'.SearchDocumentBuilder::VERSION.':'.sha1($normalized),
            now()->addWeek(),
            fn () => $this->gateway->generate('hyde', self::INSTRUCTIONS, $normalized),
        );
    }
}
