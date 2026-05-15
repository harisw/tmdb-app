<?php

namespace App\Data;

use App\Models\Title;
use Illuminate\Support\Collection;

class EnrichmentContext
{
    public ?Collection $keywordIds = null;

    public ?Collection $titleProvidersData = null;

    public function __construct(public readonly Title $title) {}
}
