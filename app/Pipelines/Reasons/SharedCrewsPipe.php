<?php

namespace App\Pipelines\Reasons;

use App\Data\Contexts\ReasonContext;
use App\Data\DTOs\SimilarityReasonData;
use App\Domain\Enums\CreditJob;
use App\Domain\Enums\ReasonType;
use App\Models\Title;
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
        $sharedCrews = $context->sharedNames(fn (Title $title) => $title->keyCrews->where('pivot.job', $job));
        if (! empty($sharedCrews)) {
            $context->addReason(new SimilarityReasonData(
                type: ReasonType::sharedCrew($job),
                values: $sharedCrews,
                weight: count($sharedCrews) * 0.37,
            ));
        }

    }
}
