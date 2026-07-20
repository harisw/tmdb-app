<?php

namespace App\Pipelines\Reasons;

use App\Data\Contexts\ReasonContext;
use Closure;

class SharedSemanticsPipe
{
    private const SEMANTIC_SIMILARITY = 'semantic_similarity';

    public function handle(ReasonContext $context, Closure $next): ReasonContext
    {
        $context->addReason([
            'type' => self::SEMANTIC_SIMILARITY,
            'values' => [number_format($context->score * 100, 1).'% match'],
            'weight' => $context->score,
        ]);

        return $next($context);
    }
}
