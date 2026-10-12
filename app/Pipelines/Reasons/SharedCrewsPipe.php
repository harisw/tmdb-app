<?php

namespace App\Pipelines\Reasons;

use App\Data\Contexts\ReasonContext;
use App\Domain\Enums\CreditJob;
use Closure;

class SharedCrewsPipe
{
    public function handle(ReasonContext $context, Closure $next): ReasonContext
    {
        collect(CreditJob::getCrewJobs())
            ->each(fn ($job) => $this->addSharedCrews($context, $job));

        return $next($context);
    }

    private function addSharedCrews(ReasonContext $context, CreditJob $job): void
    {
        $sharedCrews = $context->titleA->keyCrews->where('job', $job)->pluck('name')
            ->intersect($context->titleB->keyCrews->where('job', $job)->pluck('name'))
            ->values()
            ->all();
        if (! empty($sharedCrews)) {
            $context->addReason([
                'type' => "shared_{$job->value}",
                'values' => $sharedCrews,
                'weight' => count($sharedCrews) * 0.37,
            ]);
        }

    }
}
