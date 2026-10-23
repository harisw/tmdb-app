<?php

namespace App\Utils\Enum;

/**
 * Operational feature flags, toggled at runtime from /admin/feature-flags.
 */
enum FeatureFlag: string
{
    case KnowledgeGraphEnrichment = 'knowledge-graph-enrichment';
    case HydeSearch = 'hyde-search';

    public function default(): bool
    {
        return match ($this) {
            self::KnowledgeGraphEnrichment => false,
            self::HydeSearch => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::KnowledgeGraphEnrichment => 'Knowledge graph enrichment',
            self::HydeSearch => 'HyDE search',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::KnowledgeGraphEnrichment => 'Runs every three hours and sends titles to OpenAI to build knowledge graphs. Turning this off stops new batches from being queued; work already on the queue still finishes.',
            self::HydeSearch => 'Replaces semantic search with an LLM-written hypothetical title document, and searches the title the LLM guesses, on the Find more page. Each new query costs one LLM completion and one embedding request; skipped when a title name already matches the query closely, and falls back to hybrid search when every LLM provider is unavailable.',
        };
    }
}
