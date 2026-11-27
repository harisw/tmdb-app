<?php

namespace App\Models;

use App\Domain\Enums\CreditJob;
use App\Domain\Enums\TitleSort;
use App\Domain\Enums\TitleType;
use App\Helpers\DateTimeHelper;
use App\Models\Scopes\AvoidedKeywordScope;
use App\Models\Scopes\TitleTypeScope;
use Database\Factories\TitleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property TitleType $type
 * @property mixed $rating
 * @property string $status
 * @property Carbon|null $release_date
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
 * @property \Spatie\SchemalessAttributes\SchemalessAttributes|null $extra_attributes
 * @property string|null $enriched_at
 * @property string|null $original_name
 * @property string|null $credits_filled_at
 * @property string|null $embedding
 * @property Carbon|null $similarities_materialized_at
 * @property-read Collection<int, TitlePerson> $cast
 * @property-read int|null $cast_count
 * @property-read Collection<int, TitlePerson> $crew
 * @property-read int|null $crew_count
 * @property-read Language $language
 * @property-read Collection<int, Provider> $providers
 * @property-read int|null $providers_count
 * @property-read Collection<int, TitleProvider> $titleProviders
 * @property-read int|null $title_providers_count
 *
 * @method static Builder<static>|Title whereCreditsFilledAt($value)
 * @method static Builder<static>|Title whereEmbedding($value)
 * @method static Builder<static>|Title whereEnrichedAt($value)
 * @method static Builder<static>|Title whereExtraAttributes($value)
 * @method static Builder<static>|Title whereOriginalName($value)
 * @method static Builder<static>|Title withExtraAttributes()
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
        static::addGlobalScope(new AvoidedKeywordScope);
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->resolveRouteBindingQuery(
            $this->newQuery()->withoutGlobalScope(AvoidedKeywordScope::class),
            $value,
            $field,
        )->first();
    }

    public const IMG_SMALL_URL = 'w300';

    public const IMG_MEDIUM_URL = 'w780';

    public const IMG_LARGE_URL = 'w1280';

    private const BACKDROP_PLACEHOLDER = '/backdrop-placeholder.svg';

    private const POSTER_PLACEHOLDER = '/poster-placeholder.svg';

    protected $guarded = [];

    protected $hidden = ['embedding'];

    protected $casts = [
        'runtime' => 'string',
        'release_date' => 'datetime:Y',
        'rating' => 'float',
        'type' => TitleType::class,
        'similarities_materialized_at' => 'datetime',
        'extra_attributes' => SchemalessAttributes::class,
    ];

    public function getRuntimeAttribute(int|string|null $value): ?string
    {
        return $this->type === TitleType::MOVIE ? DateTimeHelper::minutesToHours($value) : null;
    }

    /**
     * @return Attribute<string, never>
     */
    public function posterPath(): Attribute
    {
        return Attribute::make(
            get: fn (?string $val) => $val ? config('services.tmdb.img_url').self::IMG_SMALL_URL.$val
                : self::POSTER_PLACEHOLDER
        );
    }

    /**
     * @return Attribute<string, never>
     */
    public function backdropPath(): Attribute
    {
        return Attribute::make(
            get: fn (?string $val) => $val ? config('services.tmdb.img_url').self::IMG_LARGE_URL.$val
                : self::BACKDROP_PLACEHOLDER
        );
    }

    /**
     * @return Builder<Title>
     */
    public function scopeWithExtraAttributes(): Builder
    {
        return $this->extra_attributes->modelScope();
    }

    /**
     * A listing of titles: detail relations eager-loaded, newest first.
     * Works on the model (`Title::listing()`) and on relations
     * (`$genre->titles()->listing()`).
     *
     * @param  Builder<Title>  $query
     * @return Builder<Title>
     */
    public function scopeListing(Builder $query): Builder
    {
        return $query->with(self::detailRelations())->latest('titles.created_at')->orderByDesc('titles.id');
    }

    /**
     * @param  Builder<Title>  $query
     * @return Builder<Title>
     */
    public function scopeSorted(Builder $query, TitleSort $sort): Builder
    {
        $ordered = match ($sort) {
            TitleSort::POPULAR => $query->orderByDesc('titles.popularity'),
            TitleSort::NEWEST => $query->whereNotNull('titles.release_date')->orderByDesc('titles.release_date'),
            TitleSort::RECENTLY_ADDED => $query->orderByDesc('titles.created_at'),
        };

        return $ordered->orderBy('titles.id');
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * @return BelongsToMany<Genre, $this>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    /**
     * @return BelongsToMany<ProductionCompany, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(ProductionCompany::class, 'title_production_company');
    }

    /**
     * @return BelongsToMany<Keyword, $this>
     */
    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class);
    }

    /**
     * @return BelongsTo<TitleCollection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(TitleCollection::class);
    }

    /**
     * @return BelongsToMany<Provider, $this>
     */
    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'title_providers', 'title_id', 'provider_id');
    }

    /**
     * @return HasMany<TitleProvider, $this>
     */
    public function titleProviders(): HasMany
    {
        return $this->hasMany(TitleProvider::class);
    }

    /**
     * @return array{id: int, slug: string, name: string, poster_path: string, year: ?string, rating: mixed, type: string}
     */
    public function toGraphNode(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'poster_path' => $this->poster_path,
            'year' => $this->release_date?->format('Y'),
            'rating' => $this->rating,
            'type' => $this->type->value,
        ];
    }

    /**
     * @return BelongsToMany<self, $this, TitleSimilarity>
     */
    public function similarTitles(): BelongsToMany
    {
        return $this->belongsToMany(__CLASS__, 'title_similarities', 'title_id', 'similar_title_id')
            ->using(TitleSimilarity::class)
            ->withPivot(['score', 'reasons'])
            ->orderByPivot('score', 'desc');
    }

    /**
     * @return BelongsToMany<Person, $this, TitlePerson>
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'title_person')
            ->using(TitlePerson::class)
            ->withPivot(['job', 'character', 'order', 'episode_count'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Person, $this, TitlePerson>
     */
    public function topCasts(): BelongsToMany
    {
        return $this->people()
            ->wherePivot('job', CreditJob::CAST)
            ->wherePivot('order', '<=', 5)
            ->orderByPivot('order');
    }

    /**
     * @return BelongsToMany<Person, $this, TitlePerson>
     */
    public function keyCrews(): BelongsToMany
    {
        return $this->people()
            ->wherePivotIn('job', CreditJob::getCrewJobs())
            ->orderByPivot('order');
    }

    /**
     * @return HasMany<TitlePerson, $this>
     */
    public function credits(): HasMany
    {
        return $this->hasMany(TitlePerson::class);
    }

    /**
     * @return HasOne<UserTitleState, $this>
     */
    public function userState(): HasOne
    {
        return $this->hasOne(UserTitleState::class);
    }

    /**
     * @return HasOne<TitleSearchEmbedding, $this>
     */
    public function searchEmbedding(): HasOne
    {
        return $this->hasOne(TitleSearchEmbedding::class);
    }

    /**
     * @return HasMany<TitlePerson, $this>
     */
    public function cast(): HasMany
    {
        return $this->credits()->where('job', CreditJob::CAST)->orderBy('order');
    }

    /**
     * Crew is defined by exclusion — CreditJob has no CREW case, it has the
     * specific jobs we model (director, writer, composer...).
     *
     * @return HasMany<TitlePerson, $this>
     */
    public function crew(): HasMany
    {
        return $this->credits()->whereNot('job', CreditJob::CAST);
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function detailRelations(): array
    {
        $countryId = auth()->user()?->country_id;
        if (! $countryId && request('country')) {
            $countryId = Country::whereIso2(request('country'))->first()?->id;
        }

        $relations = ['genres', 'keywords', 'titleProviders' => function ($q) use ($countryId) {
            $q->when($countryId, function ($q) use ($countryId) {
                $q->where('country_id', $countryId);
            })->with('provider')->uniqueByProvider();
        }];

        if ($userId = auth()->id()) {
            $relations['userState'] = fn ($q) => $q->where('user_id', $userId);
        }

        return $relations;
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function cardRelations(): array
    {
        $relations = array_filter(self::detailRelations(), fn ($relation) => ! in_array($relation, ['genres', 'keywords'], true));

        return [
            'genres' => fn ($q) => $q->select(['genres.id', 'genres.slug', 'genres.name']),
            ...$relations,
        ];
    }
}
