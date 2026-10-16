<?php

namespace App\Services\Search;

use App\Domain\Enums\CreditJob;
use App\Models\Person;
use App\Models\Title;

class SearchDocumentBuilder
{
    public const VERSION = 1;

    public const CREDIT_JOBS = [CreditJob::CAST, CreditJob::DIRECTOR, CreditJob::WRITER];

    private const CAST_LIMIT_ORDER = 5;

    public function build(Title $title): string
    {
        return collect([
            'Title' => $title->name,
            'Original title' => $title->original_name !== $title->name ? $title->original_name : null,
            'Type' => $title->type->value,
            'Tagline' => $title->tagline,
            'Overview' => $title->overview,
            'Genres' => $title->genres->pluck('name')->join(', '),
            'Keywords' => $title->keywords->pluck('name')->join(', '),
            'Cast' => $this->peopleFor($title, CreditJob::CAST),
            'Directors' => $this->peopleFor($title, CreditJob::DIRECTOR),
            'Writers' => $this->peopleFor($title, CreditJob::WRITER),
            'Year' => $title->release_date?->format('Y'),
            'Part of collection' => $title->collection?->name,
        ])
            ->filter(fn (?string $value) => filled($value))
            ->map(fn (string $value, string $label) => "{$label}: {$value}")
            ->join("\n");
    }

    private function peopleFor(Title $title, CreditJob $job): string
    {
        return $title->people
            ->filter(fn (Person $person) => $person->pivot->job === $job)
            ->when($job === CreditJob::CAST, fn ($people) => $people->filter(
                fn (Person $person) => $person->pivot->order <= self::CAST_LIMIT_ORDER
            ))
            ->sortBy(fn (Person $person) => $person->pivot->order)
            ->pluck('name')
            ->join(', ');
    }
}
