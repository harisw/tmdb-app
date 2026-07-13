<?php

namespace App\Models;

use App\Casts\RoundFloat;
use App\Domain\Enums\CreditJob;
use App\Domain\Enums\TitleType;
use App\Helpers\DateTimeHelper;
use App\Models\Scopes\AdultContentScope;
use App\Models\Scopes\TitleTypeScope;
use Database\Factories\TitleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property TitleType $type
 * @property mixed $rating
 * @property string $status
 * @property Carbon $release_date
 * @property int $revenue
 * @property string $runtime
 * @property string|null $backdrop_path
 * @property string|null $imdb_id
 * @property string|null $overview
 * @property float $popularity
 * @property string|null $poster_path
 * @property string|null $tagline
 * @property int|null $tmdb_id
 * @property string|null $homepage
 * @property int $language_id
 * @property int|null $collection_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TitleCollection|null $collection
 * @property-read Collection<int, ProductionCompany> $companies
 * @property-read int|null $companies_count
 * @property-read Collection<int, Genre> $genres
 * @property-read int|null $genres_count
 * @property-read Collection<int, Keyword> $keywords
 * @property-read int|null $keywords_count
 * @property-read Collection<int, Person> $people
 * @property-read int|null $people_count
 * @property-read Collection<int, TitlePerson> $credits
 * @property-read int|null $credits_count
 *
 * @method static \Database\Factories\TitleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereBackdropPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereCollectionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereHomepage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereImdbId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereLanguageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereOverview($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title wherePopularity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title wherePosterPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereReleaseDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereRuntime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereTagline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereTmdbId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Title extends Model
{
    /** @use HasFactory<TitleFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new TitleTypeScope);
        static::addGlobalScope(new AdultContentScope);
    }

    public const IMG_SMALL_URL = 'w300';

    public const IMG_MEDIUM_URL = 'w780';

    public const IMG_LARGE_URL = 'w1280';

    protected $guarded = [];

    protected $casts = [
        'runtime' => 'string',
        'release_date' => 'datetime:Y',
        'rating' => RoundFloat::class,
        'type' => TitleType::class,
        'is_adult' => 'boolean',
        'extra_attributes' => SchemalessAttributes::class,
    ];

    public function getRuntimeAttribute($value): ?string
    {
        return $this->type === TitleType::MOVIE ? DateTimeHelper::minutesToHours($value) : null;
    }

    public function scopeWithExtraAttributes(): Builder
    {
        return $this->extra_attributes->modelScope();
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(ProductionCompany::class, 'title_production_company');
    }

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(TitleCollection::class);
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'title_providers', 'title_id', 'provider_id');
    }

    public function titleProviders(): HasMany
    {
        return $this->hasMany(TitleProvider::class);
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'title_person')
            ->using(TitlePerson::class)
            ->withPivot(['role', 'character', 'order'])
            ->withTimestamps();
    }

    public function credits(): HasMany
    {
        return $this->hasMany(TitlePerson::class);
    }

    public function cast(): HasMany
    {
        return $this->credits()->where('role', CreditJob::CAST)->orderBy('order');
    }

    public function crew(): HasMany
    {
        return $this->credits()->where('role', CreditJob::CREW);
    }
}
