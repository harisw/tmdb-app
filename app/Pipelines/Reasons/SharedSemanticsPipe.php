<?php

namespace App\Pipelines\Reasons;

use App\Data\Contexts\ReasonContext;
use App\Data\DTOs\SimilarityReasonData;
use App\Domain\Enums\ReasonType;
use Closure;

class SharedSemanticsPipe
{
    public function handle(ReasonContext $context, Closure $next): ReasonContext
    {
        $context->addReason(new SimilarityReasonData(
            type: ReasonType::SEMANTIC_SIMILARITY,
            values: [number_format($context->score * 100, 1).'% match'],
            weight: $context->score,
        ));

        return $next($context);
    }
}
