<?php

namespace App\Models;

use App\Domain\Enums\CreditJob;
use App\Domain\Enums\TitleType;
use App\Helpers\DateTimeHelper;
use App\Infrastructure\Observers\TitleObserver;
use App\Models\Scopes\AdultContentScope;
use App\Models\Scopes\TitleTypeScope;
use Database\Factories\TitleFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
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
 * @property \Spatie\SchemalessAttributes\SchemalessAttributes|null $extra_attributes
 * @property string|null $enriched_at
 * @property bool $is_adult
 * @property string|null $original_name
 * @property string|null $credits_filled_at
 * @property string|null $embedding
 * @property-read Collection<int, TitlePerson> $cast
 * @property-read int|null $cast_count
 * @property-read Collection<int, TitlePerson> $crew
 * @property-read int|null $crew_count
 * @property-read Language $language
 * @property-read TitlePerson|null $pivot
 * @property-read Collection<int, Provider> $providers
 * @property-read int|null $providers_count
 * @property-read Collection<int, TitleProvider> $titleProviders
 * @property-read int|null $title_providers_count
 *
 * @method static Builder<static>|Title whereCreditsFilledAt($value)
 * @method static Builder<static>|Title whereEmbedding($value)
 * @method static Builder<static>|Title whereEnrichedAt($value)
 * @method static Builder<static>|Title whereExtraAttributes($value)
 * @method static Builder<static>|Title whereIsAdult($value)
 * @method static Builder<static>|Title whereOriginalName($value)
 * @method static Builder<static>|Title withExtraAttributes()
 *
 * @mixin \Eloquent
 */
#[ObservedBy([TitleObserver::class])]
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

    private const BACKDROP_PLACEHOLDER = '/backdrop-placeholder.svg';

    private const POSTER_PLACEHOLDER = '/poster-placeholder.svg';

    protected $guarded = [];

    protected $hidden = ['embedding'];

    protected $casts = [
        'runtime' => 'string',
        'release_date' => 'datetime:Y',
        'rating' => 'float',
        'type' => TitleType::class,
        'is_adult' => 'boolean',
        'extra_attributes' => SchemalessAttributes::class,
    ];

    public function getRuntimeAttribute($value): ?string
    {
        return $this->type === TitleType::MOVIE ? DateTimeHelper::minutesToHours($value) : null;
    }

    public function posterPath(): Attribute
    {
        return Attribute::make(
            get: fn (?string $val) => $val ? config('services.tmdb.img_url').self::IMG_SMALL_URL.$val
                : self::POSTER_PLACEHOLDER
        );
    }

    public function backdropPath(): Attribute
    {
        return Attribute::make(
            get: fn (?string $val) => $val ? config('services.tmdb.img_url').self::IMG_LARGE_URL.$val
                : self::BACKDROP_PLACEHOLDER
        );
    }

    public function scopeWithExtraAttributes(): Builder
    {
        return $this->extra_attributes->modelScope();
    }

    /**
     * A listing of titles: detail relations eager-loaded, newest first.
     * Works on the model (`Title::listing()`) and on relations
     * (`$genre->titles()->listing()`).
     */
    public function scopeListing(Builder $query): Builder
    {
        return $query->with(self::detailRelations())->latest();
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

    public function similarTitles(): BelongsToMany
    {
        return $this->belongsToMany(__CLASS__, 'title_similarities', 'title_id', 'similar_title_id')
            ->withPivot(['score', 'reasons'])
            ->orderByPivot('score', 'desc');
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'title_person')
            ->using(TitlePerson::class)
            ->withPivot(['job', 'character', 'order', 'episode_count'])
            ->withTimestamps();
    }

    public function topCasts(): BelongsToMany
    {
        return $this->people()
            ->wherePivot('job', CreditJob::CAST)
            ->wherePivot('order', '<=', 5)
            ->orderByPivot('order');
    }

    public function keyCrews(): BelongsToMany
    {
        return $this->people()
            ->wherePivotIn('job', CreditJob::getCrewJobs())
            ->orderByPivot('order');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(TitlePerson::class);
    }

    public function userState(): HasOne
    {
        return $this->hasOne(UserTitleState::class);
    }

    public function cast(): HasMany
    {
        return $this->credits()->where('job', CreditJob::CAST)->orderBy('order');
    }

    /**
     * Crew is defined by exclusion — CreditJob has no CREW case, it has the
     * specific jobs we model (director, writer, composer...).
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
}
