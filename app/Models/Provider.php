<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tmdb_id
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
 * @property int $count
 * @property-read Collection<int, Title> $titles
 * @property-read int|null $titles_count
 * @property-read mixed $url
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereTmdbId($value)
 *
 * @mixin \Eloquent
 */
class Provider extends Model
{
    use HasFactory;

    protected $fillable = [
        'tmdb_id',
        'slug',
        'name',
        'img_logo',
        'priority',
    ];

    protected $appends = ['url'];

    protected $casts = [
        'tmdb_id' => 'integer',
        'slug' => 'string',
        'name' => 'string',
        'img_logo' => 'string',
        'priority' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => route('titles-by-provider', $attributes['slug'])
        );
    }

    public function titles(): BelongsToMany
    {
        return $this->belongsToMany(Title::class, 'title_providers');
    }

    public function countryProviders(): HasMany
    {
        return $this->hasMany(CountryProvider::class);
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'country_providers')
            ->withPivot('priority');
    }

    public function mainProvider(): BelongsTo
    {
        return $this->belongsTo(self::class, 'main_provider_id');
    }
}
