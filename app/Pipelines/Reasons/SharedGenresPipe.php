<?php

namespace App\Pipelines\Reasons;

use App\Data\Contexts\ReasonContext;
use Closure;

class SharedGenresPipe
{
    private const SHARED_GENRES = 'shared_genres';

    public function handle(ReasonContext $context, Closure $next): ReasonContext
    {
        $shared = $context->titleA->genres->pluck('name')
            ->intersect($context->titleB->genres->pluck('name'))
            ->values()
            ->all();

        if (! empty($shared)) {
            $context->addReason([
                'type' => self::SHARED_GENRES,
                'values' => $shared,
                'weight' => count($shared) * 0.2,
            ]);
        }

        return $next($context);
    }
}
