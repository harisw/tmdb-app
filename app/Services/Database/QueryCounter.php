<?php

namespace App\Services\Database;

class QueryCounter
{
    private int $queries = 0;

    private float $milliseconds = 0.0;

    public function record(float $milliseconds): void
    {
        $this->queries++;
        $this->milliseconds += $milliseconds;
    }

    public function queries(): int
    {
        return $this->queries;
    }

    public function milliseconds(): float
    {
        return $this->milliseconds;
    }
}
