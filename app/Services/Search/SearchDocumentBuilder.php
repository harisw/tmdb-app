<?php

namespace App\Services\Search;

use App\Domain\Enums\CreditJob;
use App\Models\Person;
use App\Models\Title;

class SearchDocumentBuilder
{
    public const VERSION = 2;

    private const KEYWORD_LIMIT = 10;

    private const CREW_LIMIT = 2;

    private const CAST_LIMIT = 5;

    public const CREDIT_JOBS = [CreditJob::CAST, CreditJob::DIRECTOR, CreditJob::WRITER];

    /**
     * @return array<int|string, string|\Closure>
     */
    public static function relations(): array
    {
        return [
            'genres',
            'keywords',
            'collection',
            'people' => fn ($query) => $query->wherePivotIn('job', self::CREDIT_JOBS),
        ];
    }

    public function build(Title $title): string
    {
        $directors = $this->peopleFor($title, CreditJob::DIRECTOR, self::CREW_LIMIT);
        $writers = $this->peopleFor($title, CreditJob::WRITER, self::CREW_LIMIT);
        $cast = $this->peopleFor($title, CreditJob::CAST, self::CAST_LIMIT);

        return collect([
            $title->tagline ? "{$title->name}: {$title->tagline}" : $title->name,
            $title->overview,
            $title->genres->isNotEmpty() ? 'Genres: '.$title->genres->pluck('name')->join(', ') : null,
            $title->keywords->isNotEmpty() ? 'Themes:  '.$title->keywords->take(self::KEYWORD_LIMIT)->pluck('name')->join(', ') : null,
            $directors ? "Directed by {$directors}." : null,
            $writers ? "Written by {$writers}." : null,
            $cast ? "Starring {$cast}." : null,
        ])->filter()->join("\n");
    }

    private function peopleFor(Title $title, CreditJob $job, int $limit): string
    {
        return $title->people
            ->filter(fn (Person $person) => $person->pivot->job === $job)
            ->sortBy(fn (Person $person) => $person->pivot->order)
            ->take($limit)
            ->pluck('name')
            ->join(', ');
    }
}
