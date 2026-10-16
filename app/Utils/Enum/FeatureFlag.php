<?php

namespace App\Utils\Enum;

/**
 * Operational feature flags, toggled at runtime from /admin/feature-flags.
 */
enum FeatureFlag: string
{
    case KnowledgeGraphEnrichment = 'knowledge-graph-enrichment';
    case HybridSearch = 'hybrid-search';

    public function default(): bool
    {
        return match ($this) {
            self::KnowledgeGraphEnrichment => false,
            self::HybridSearch => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::KnowledgeGraphEnrichment => 'Knowledge graph enrichment',
            self::HybridSearch => 'Hybrid search',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::KnowledgeGraphEnrichment => 'Runs every three hours and sends titles to OpenAI to build knowledge graphs. Turning this off stops new batches from being queued; work already on the queue still finishes.',
            self::HybridSearch => 'Blends full-text and semantic (OpenAI embedding) results on the Find more page. Each new query costs one embedding request; falls back to full-text only when OpenAI is unavailable.',
        };
    }
}
