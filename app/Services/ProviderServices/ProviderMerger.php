<?php

namespace App\Services\ProviderServices;

use App\Models\Provider;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProviderMerger
{
    public function merge(Provider $from, Provider $into): void
    {
        if ($from->is($into)) {
            throw new InvalidArgumentException("Cannot merge provider [{$from->id}] into itself");
        }

        $bindings = ['from' => $from->id, 'into' => $into->id];

        DB::transaction(function () use ($bindings) {
            DB::table('provider_aliases')->where('provider_id', $bindings['from'])->update(['provider_id' => $bindings['into']]);
            $this->moveTitleOffers($bindings);
            $this->moveCountryLinks($bindings);
            DB::table('providers')->where('id', $bindings['from'])->delete();
            $this->recountTitles($bindings['into']);
        });

        (new Provider)->flushCache();
    }

    /**
     * @param  array{from: int, into: int}  $bindings
     */
    private function moveTitleOffers(array $bindings): void
    {
        DB::delete('
            DELETE FROM title_providers AS offer
            USING country_providers AS from_link, country_providers AS into_link, title_providers AS kept
            WHERE offer.country_provider_id = from_link.id AND from_link.provider_id = :from
              AND into_link.country_id = from_link.country_id AND into_link.provider_id = :into
              AND kept.country_provider_id = into_link.id
              AND kept.title_id = offer.title_id AND kept.type = offer.type
        ', $bindings);

        DB::update('
            UPDATE title_providers AS offer
            SET country_provider_id = into_link.id, provider_id = :into
            FROM country_providers AS from_link, country_providers AS into_link
            WHERE offer.country_provider_id = from_link.id AND from_link.provider_id = :from
              AND into_link.country_id = from_link.country_id AND into_link.provider_id = :into
        ', $bindings);

        DB::table('title_providers')->where('provider_id', $bindings['from'])->update(['provider_id' => $bindings['into']]);
    }

    /**
     * @param  array{from: int, into: int}  $bindings
     */
    private function moveCountryLinks(array $bindings): void
    {
        DB::delete('
            DELETE FROM country_providers AS from_link
            USING country_providers AS into_link
            WHERE from_link.provider_id = :from
              AND into_link.country_id = from_link.country_id AND into_link.provider_id = :into
        ', $bindings);

        DB::table('country_providers')->where('provider_id', $bindings['from'])->update(['provider_id' => $bindings['into']]);
    }

    private function recountTitles(int $providerId): void
    {
        DB::update('
            UPDATE providers
            SET count = (SELECT COUNT(DISTINCT title_id) FROM title_providers WHERE provider_id = providers.id)
            WHERE id = :id
        ', ['id' => $providerId]);
    }
}
