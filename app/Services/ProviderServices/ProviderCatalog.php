<?php

namespace App\Services\ProviderServices;

use App\Models\Country;
use App\Models\Provider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProviderCatalog
{
    private Collection $providersBySlug;

    private Collection $countryIdsByIso2;

    public function __construct()
    {
        $this->providersBySlug = Provider::get()->keyBy('slug');
        $this->countryIdsByIso2 = Country::pluck('id', 'iso2');
    }

    public function providerId(?string $slug): ?int
    {
        $provider = $slug ? $this->providersBySlug->get($slug) : null;
        if (! $provider) {
            Log::warning("Provider [$slug] not found");

            return null;
        }

        return $provider->main_provider_id ?? $provider->id;
    }

    public function countryId(string $iso2): ?int
    {
        $countryId = $this->countryIdsByIso2->get(strtoupper($iso2));
        if (! $countryId) {
            Log::warning("Country [$iso2] not found");
        }

        return $countryId;
    }
}
