<?php

namespace App\Models\Observers;

use App\Models\Title;
use App\Services\KnowledgeGraph\TitleEnrichmentService;

class TitleObserver
{
    private const EMBEDDING_FIELDS = ['name', 'tagline', 'overview', 'release_year', 'collection_id'];

    public function __construct(private readonly TitleEnrichmentService $enrichmentService) {}

    public function created(Title $title): void
    {
        $this->enrichmentService->enqueue($title);
    }
}
