<?php

namespace App\Services\Search;

use Illuminate\Support\Collection;

readonly class LexicalMatches
{
    /**
     * @param  Collection<int, float>  $fullText
     * @param  Collection<int, float>  $trigram
     */
    public function __construct(
        public Collection $fullText,
        public Collection $trigram,
        public bool $confident,
    ) {}

    /**
     * @return array<int, Collection<int, float>>
     */
    public function lists(): array
    {
        return [$this->fullText, $this->trigram];
    }
}
