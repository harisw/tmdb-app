<?php

namespace App\Services\KnowledgeGraph;

use App\Data\Contexts\ReasonContext;
use App\Data\DTOs\SimilarityReasonData;
use App\Models\Title;
use App\Pipelines\Reasons\SharedCastsPipe;
use App\Pipelines\Reasons\SharedCrewsPipe;
use App\Pipelines\Reasons\SharedGenresPipe;
use App\Pipelines\Reasons\SharedKeywordsPipe;
use App\Pipelines\Reasons\SharedSemanticsPipe;
use Illuminate\Pipeline\Pipeline;

readonly class ReasonBuilderService
{
    public function __construct(private Pipeline $pipeline) {}

    /**
     * @return list<SimilarityReasonData>
     */
    public function build(Title $a, Title $b, float $score): array
    {
        $context = new ReasonContext($a, $b, $score);

        return $this->pipeline
            ->send($context)
            ->through([
                SharedGenresPipe::class,
                SharedKeywordsPipe::class,
                SharedCastsPipe::class,
                SharedCrewsPipe::class,
                SharedSemanticsPipe::class,
            ])
            ->thenReturn()
            ->getSortedReasons();
    }
}
