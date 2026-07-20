<?php

namespace App\Services\KnowledgeGraph;

use App\Domain\Enums\CreditJob;
use App\Models\Title;

class TitleDocumentBuilder
{
    /**
     * @throws \Exception
     */
    public function build(Title $title): string
    {
        $parts = [];

        $parts[] = "Title: {$title->name}";

        if ($title->genres->isNotEmpty()) {
            $parts[] = 'Genres: '.$title->genres->take(2)->pluck('name')->join(', ');
        }

        if ($title->keywords->isNotEmpty()) {
            $parts[] = 'Keywords: '.$title->keywords->pluck('name')->join(', ');
        }

        $this->buildCastDoc($title, $parts);

        collect([CreditJob::DIRECTOR,
            CreditJob::PRODUCER,
            CreditJob::COMPOSER,
            CreditJob::WRITER,
            CreditJob::ART_DIRECTOR])->each(function ($job) use ($title, &$parts) {
                $this->buildCrewDoc($title, $job, $parts);
            });

        // Contextual signals
        if ($title->release_date) {
            $parts[] = "Year: {$title->release_date->format('Y')}";
        }

        if ($title->collection) {
            $parts[] = "Part of collection: {$title->collection->name}";
        }

        return implode("\n", $parts);
    }

    private function buildCastDoc(Title $title, array &$parts): void
    {
        $cast = $title->people()
            ->wherePivot('job', CreditJob::CAST)
            ->wherePivot('order', '<=', 5)
            ->orderByPivot('order')
            ->pluck('name');

        if ($cast->isNotEmpty()) {
            $parts[] = 'Cast: '.$cast->join(', ');
        }
    }

    private function buildCrewDoc(Title $title, CreditJob $job, array &$parts): void
    {
        $people = $title->people()
            ->wherePivot('job', $job)
            ->orderByPivot('order')
            ->pluck('name');

        if ($people->isNotEmpty()) {
            $parts[] = match ($job) {
                CreditJob::DIRECTOR => 'Directors: ',
                CreditJob::COMPOSER => 'composer: ',
                CreditJob::PRODUCER => 'Producers: ',
                CreditJob::WRITER => 'Writers: ',
                CreditJob::ART_DIRECTOR => 'Art Directors: ',
                default => throw new \Exception("Job '{$job}' not found"),
            }.$people->join(', ');
        }
    }
}
