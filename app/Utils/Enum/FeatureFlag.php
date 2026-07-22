<?php

namespace App\Utils\Enum;

/**
 * Operational feature flags, toggled at runtime from /admin/feature-flags.
 */
enum FeatureFlag: string
{
    case KnowledgeGraphEnrichment = 'knowledge-graph-enrichment';

    public function default(): bool
    {
        return match ($this) {
            self::KnowledgeGraphEnrichment => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::KnowledgeGraphEnrichment => 'Knowledge graph enrichment',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::KnowledgeGraphEnrichment => 'Runs every three hours and sends titles to OpenAI to build knowledge graphs. Turning this off stops new batches from being queued — work already on the queue still finishes.',
        };
    }
}
