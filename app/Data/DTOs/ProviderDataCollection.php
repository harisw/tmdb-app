<?php

namespace App\Data\DTOs;

use Spatie\LaravelData\DataCollection;

class ProviderDataCollection extends DataCollection
{
    public function rows(): array
    {
        return collect($this->items())
            ->map(fn (ProviderData $data) => $data->toUpsertArray())
            ->keyBy('slug')
            ->values()
            ->all();
    }

    public function uniqueBy(): array
    {
        return ['slug'];
    }

    public function updateColumns(): array
    {
        return ['name', 'slug', 'img_logo', 'priority'];
    }
}
