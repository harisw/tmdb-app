<?php

namespace App\Pipelines\Reasons;

use App\Data\Contexts\ReasonContext;
use App\Data\DTOs\SimilarityReasonData;
use App\Domain\Enums\ReasonType;
use App\Models\Title;
use Closure;

class SharedGenresPipe
{
    public function handle(ReasonContext $context, Closure $next): ReasonContext
    {
        $shared = $context->sharedNames(fn (Title $title) => $title->genres);

        if (! empty($shared)) {
            $context->addReason(new SimilarityReasonData(
                type: ReasonType::SHARED_GENRES,
                values: $shared,
                weight: count($shared) * 0.2,
            ));
        }

        return $next($context);
    }
}
