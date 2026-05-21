<?php

namespace App\Data;

use App\Models\Title;

readonly class EnrichmentContext
{
    public function __construct(public Title $title) {}
}
