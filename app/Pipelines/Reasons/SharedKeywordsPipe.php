<?php

namespace App\Pipelines\Reasons;

use App\Data\Contexts\ReasonContext;
use Closure;

class SharedKeywordsPipe
{
    private const SHARED_KEYWORDS = 'shared_keywords';

    public function handle(ReasonContext $context, Closure $next): ReasonContext
    {
        $shared = $context->titleA->keywords->pluck('name')
            ->intersect($context->titleB->keywords->pluck('name'))
            ->values()
            ->all();

        if (! empty($shared)) {
            $context->addReason([
                'type' => self::SHARED_KEYWORDS,
                'values' => $shared,
                'weight' => count($shared) * 0.4,
            ]);
        }

        return $next($context);
    }
}
