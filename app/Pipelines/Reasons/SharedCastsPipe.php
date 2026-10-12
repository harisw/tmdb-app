<?php

namespace App\Pipelines\Reasons;

use App\Data\Contexts\ReasonContext;
use Closure;

class SharedCastsPipe
{
    private const SHARED_CASTS = 'shared_casts';

    public function handle(ReasonContext $context, Closure $next): ReasonContext
    {
        $sharedCasts = $context->titleA->topCasts->pluck('name')
            ->intersect($context->titleB->topCasts->pluck('name'))
            ->values()
            ->all();
        if (! empty($sharedCasts)) {
            $context->addReason([
                'type' => self::SHARED_CASTS,
                'values' => $sharedCasts,
                'weight' => count($sharedCasts) * 0.35,
            ]);
        }

        return $next($context);
    }
}
