<?php

namespace App\Services\KnowledgeGraph;

use App\Models\Title;

class TitleDocumentBuilder
{
    public function build(Title $title): string
    {
        $parts = [];

        $parts[] = "Title: {$title->name}";

        if ($title->genres->isNotEmpty()) {
            $parts[] = 'Genres: '.$title->genres->pluck('name')->join(', ');
        }

    }
}
