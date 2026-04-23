<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperProvider
 */
class Provider extends Model
{
    protected $fillable = [
        'tmdb_provider_id',
        'slug',
        'name',
        'img_logo',
        'priority',
    ];

    protected $casts = [
        'tmdb_provider_id' => 'integer',
        'slug' => 'string',
        'name' => 'string',
        'img_logo' => 'string',
        'priority' => 'integer',
    ];

    public function countryProviders(): HasMany
    {
        return $this->hasMany(CountryProvider::class);
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'country_providers')
            ->withPivot('priority');
    }
}
