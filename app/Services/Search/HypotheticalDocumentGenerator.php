<?php

namespace App\Services\Search;

use App\Services\Llm\LlmGateway;
use Illuminate\Support\Facades\Cache;

class HypotheticalDocumentGenerator
{
    private const PROMPT_VERSION = 3;

    private const INSTRUCTIONS = <<<'PROMPT'
        You help a movie and TV search engine. The user's message is a search query in any language.
        Work out which film or series they most likely mean and reply with a JSON object:

        title: its English title as listed on TMDB; for a franchise, the title of its first entry. Use null when the query asks for a person's filmography, a genre or anything else no single title answers.
        document: its catalogue entry in English, in exactly this format:

        <Title>
        <Two sentence plot overview>
        Genres: <comma-separated genres>
        Themes: <comma-separated themes and keywords>
        Directed by <director>.
        Written by <writers>.
        Starring <up to three lead actors>.

        When title is null, write the entry for a plausible title but leave out the title, director, writer and cast lines.
        PROMPT;

    private const RESPONSE_FORMAT = [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'hypothetical_document',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => ['string', 'null']],
                    'document' => ['type' => 'string'],
                ],
                'required' => ['title', 'document'],
                'additionalProperties' => false,
            ],
        ],
    ];

    public function __construct(private readonly LlmGateway $gateway) {}

    public function generate(string $query): HypotheticalDocument
    {
        $normalized = mb_strtolower(trim($query));

        return new HypotheticalDocument(...Cache::remember(
            'hyde-document:'.self::PROMPT_VERSION.':'.SearchDocumentBuilder::VERSION.':'.sha1($normalized),
            now()->addWeek(),
            fn () => get_object_vars(HypotheticalDocument::fromLlmOutput($this->gateway->generate(
                'generate-hypothetical-document',
                self::INSTRUCTIONS,
                $normalized,
                ['response_format' => self::RESPONSE_FORMAT],
            ))),
        ));
    }
}
