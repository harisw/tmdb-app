<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tmdb_provider_id
 * @property string $slug
 * @property string $name
 * @property string|null $img_logo
 * @property int $priority
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Country> $countries
 * @property-read int|null $countries_count
 * @property-read Collection<int, CountryProvider> $countryProviders
 * @property-read int|null $country_providers_count
 *
 * @method static \Database\Factories\ProviderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereImgLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereTmdbProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Provider extends Model
{
    use HasFactory;

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
