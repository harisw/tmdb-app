<?php

namespace App\Pipelines\Reasons;

use App\Data\Contexts\ReasonContext;
use App\Data\DTOs\SimilarityReasonData;
use App\Domain\Enums\ReasonType;
use App\Models\Title;
use Closure;

class SharedKeywordsPipe
{
    public function handle(ReasonContext $context, Closure $next): ReasonContext
    {
        $shared = $context->sharedNames(fn (Title $title) => $title->keywords->where('use_in_similarity', true));

        if (! empty($shared)) {
            $context->addReason(new SimilarityReasonData(
                type: ReasonType::SHARED_KEYWORDS,
                values: $shared,
                weight: count($shared) * 0.4,
            ));
        }

        return $next($context);
    }
}
