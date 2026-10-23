<?php

namespace App\Pipelines\Reasons;

use App\Data\Contexts\ReasonContext;
use App\Data\DTOs\SimilarityReasonData;
use App\Domain\Enums\ReasonType;
use App\Models\Title;
use Closure;

class SharedCastsPipe
{
    public function handle(ReasonContext $context, Closure $next): ReasonContext
    {
        $sharedCasts = $context->sharedNames(fn (Title $title) => $title->topCasts);
        if (! empty($sharedCasts)) {
            $context->addReason(new SimilarityReasonData(
                type: ReasonType::SHARED_CASTS,
                values: $sharedCasts,
                weight: count($sharedCasts) * 0.35,
            ));
        }

        return $next($context);
    }
}
