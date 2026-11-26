<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $iso2
 * @property string|null $iso3
 * @property int|null $language_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Language|null $language
 * @method static \Database\Factories\CountryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country whereIso2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country whereIso3($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country whereLanguageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class Country extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $country_id
 * @property int $provider_id
 * @property int $priority
 * @property SchemalessAttributes|null $extra_attributes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Country $country
 * @property-read Provider $provider
 * @method static \Database\Factories\CountryProviderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider whereExtraAttributes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class CountryProvider extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $count
 * @property-read Collection<int, Title> $titles
 * @property-read int|null $titles_count
 * @property-read mixed $url
 * @method static \Database\Factories\GenreFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class Genre extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $count
 * @property-read Collection<int, Title> $titles
 * @property-read int|null $titles_count
 * @method static \Database\Factories\KeywordFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereUpdatedAt($value)
 * @property-read mixed $url
 * @mixin \Eloquent
 * @property bool $use_in_similarity
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword matching(string $term)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereUseInSimilarity($value)
 */
	class Keyword extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $count
 * @property-read Collection<int, Title> $titles
 * @property-read int|null $titles_count
 * @method static \Database\Factories\LanguageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language whereName($value)
 * @mixin \Eloquent
 */
	class Language extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Gender $gender
 * @property bool $is_adult
 * @property string|null $profile_path
 * @property float $popularity
 * @property int|null $tmdb_id
 * @property \Spatie\SchemalessAttributes\SchemalessAttributes $extra_attributes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Title> $titles
 * @property-read int|null $titles_count
 * @property-read Collection<int, TitlePerson> $credits
 * @property-read int|null $credits_count
 * @method static \Database\Factories\PersonFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereIsAdult($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person wherePopularity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereProfilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereTmdbId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereUpdatedAt($value)
 * @property-read TitlePerson|null $pivot
 * @method static Builder<static>|Person whereExtraAttributes($value)
 * @method static Builder<static>|Person withExtraAttributes()
 * @mixin \Eloquent
 */
	class Person extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $count
 * @property-read Collection<int, Title> $titles
 * @property-read int|null $titles_count
 * @method static \Database\Factories\ProductionCompanyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class ProductionCompany extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
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
 * @property-read Collection<int, ProviderAlias> $aliases
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereUpdatedAt($value)
 * @property int $count
 * @property-read Collection<int, Title> $titles
 * @property-read int|null $titles_count
 * @property-read mixed $url
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereCount($value)
 * @mixin \Eloquent
 * @property-read int|null $aliases_count
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider all($columns = [])
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider avg($column)
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider cache(array $tags = [])
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider cachedValue(array $arguments, string $cacheKey)
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider count($columns = '*')
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider disableCache()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider disableModelCaching()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider exists()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider flushCache(array $tags = [])
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider getInnerBuilder()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider inRandomOrder($seed = '')
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider insert(array $values)
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider isCachable()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider isCacheConnectionException(\Throwable $exception)
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider max($column)
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider min($column)
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider setInnerBuilder(\Illuminate\Database\Eloquent\Builder $builder)
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider shouldFallbackToDatabase()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider sum($column)
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider truncate()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider withCacheCooldownSeconds(?int $seconds = null)
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder<static>|Provider withCacheFallback(callable $operation, string $context, ?callable $fallback = null)
 */
	class Provider extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $provider_id
 * @property ProviderSource $source
 * @property string $external_id
 * @property string|null $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Provider $provider
 * @method static \Database\Factories\ProviderAliasFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderAlias newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderAlias newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderAlias query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderAlias whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderAlias whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderAlias whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderAlias whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderAlias whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderAlias whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderAlias whereUpdatedAt($value)
 */
	class ProviderAlias extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int|null $title_id
 * @property string|null $search_vector
 * @property-read Title|null $title
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableTitle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableTitle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableTitle query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableTitle whereSearchVector($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableTitle whereTitleId($value)
 * @mixin \Eloquent
 */
	class SearchableTitle extends \Eloquent {}
}

namespace App\Models{
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
 * @method static Builder<static>|Title whereCreditsFilledAt($value)
 * @method static Builder<static>|Title whereEmbedding($value)
 * @method static Builder<static>|Title whereEnrichedAt($value)
 * @method static Builder<static>|Title whereExtraAttributes($value)
 * @method static Builder<static>|Title whereOriginalName($value)
 * @method static Builder<static>|Title withExtraAttributes()
 * @mixin \Eloquent
 * @property-read \App\Models\TitleSimilarity|\App\Models\TitlePerson|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Person> $keyCrews
 * @property-read int|null $key_crews_count
 * @property-read \App\Models\TitleSearchEmbedding|null $searchEmbedding
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Title> $similarTitles
 * @property-read int|null $similar_titles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Person> $topCasts
 * @property-read int|null $top_casts_count
 * @property-read \App\Models\UserTitleState|null $userState
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title listing()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title sorted(\App\Domain\Enums\TitleSort $sort)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereSimilaritiesMaterializedAt($value)
 */
	class Title extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $tmdb_id
 * @property string $slug
 * @property string $name
 * @property string|null $poster_path
 * @property string|null $backdrop_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleCollection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleCollection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleCollection query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleCollection whereBackdropPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleCollection whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleCollection whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleCollection whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleCollection wherePosterPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleCollection whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleCollection whereTmdbId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleCollection whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class TitleCollection extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $title_id
 * @property TitleEventType $event_type
 * @property numeric|null $value
 * @property Carbon $created_at
 * @property-read Title $title
 * @property-read User $user
 * @method static \Database\Factories\TitleInteractionEventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent whereTitleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent whereValue($value)
 * @mixin \Eloquent
 */
	class TitleInteractionEvent extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $title_id
 * @property int $person_id
 * @property CreditJob $job
 * @property string|null $character
 * @property int $order
 * @property int|null $episode_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Title $title
 * @property-read Person $person
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson whereCharacter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson whereJob($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson wherePersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson whereTitleId($value)
 * @method static Builder<static>|TitlePerson ordered()
 * @method static Builder<static>|TitlePerson whereCreatedAt($value)
 * @method static Builder<static>|TitlePerson whereEpisodeCount($value)
 * @method static Builder<static>|TitlePerson whereId($value)
 * @method static Builder<static>|TitlePerson whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class TitlePerson extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $country_provider
 * @property int $country_id
 * @property int $provider_id
 * @property int $title_id
 * @property StreamType $type
 * @property int $priority
 * @property array<array-key, mixed> $audio_languages
 * @property array<array-key, mixed> $subtitle_languages
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Country $country
 * @property-read CountryProvider $countryProvider
 * @property-read Provider $provider
 * @property-read Collection<int, TitleProviderLanguage> $providerLanguages
 * @property-read int|null $provider_languages_count
 * @property-read Title $title
 * @method static \Database\Factories\TitleProviderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereAudioLanguages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereCountryProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereSubtitleLanguages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereTitleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereUpdatedAt($value)
 * @property int $country_provider_id
 * @property string|null $tmdb_link
 * @method static Builder<static>|TitleProvider forCountry(int $countryId)
 * @method static Builder<static>|TitleProvider ofType(\App\Domain\Enums\StreamType $type)
 * @method static Builder<static>|TitleProvider uniqueByProvider()
 * @method static Builder<static>|TitleProvider whereCountryProviderId($value)
 * @method static Builder<static>|TitleProvider whereTmdbLink($value)
 * @mixin \Eloquent
 * @property \Spatie\SchemalessAttributes\SchemalessAttributes|null $extra_attributes
 * @property string|null $link
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereExtraAttributes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereLink($value)
 */
	class TitleProvider extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $title_provider_id
 * @property int $language_id
 * @property ProviderLanguageType $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Language $language
 * @property-read TitleProvider $titleProvider
 * @method static \Database\Factories\TitleProviderLanguageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage whereLanguageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage whereTitleProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class TitleProviderLanguage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $title_id
 * @property string $embedding
 * @property int $document_version
 * @property-read Title $title
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\TitleSearchEmbeddingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSearchEmbedding newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSearchEmbedding newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSearchEmbedding query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSearchEmbedding whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSearchEmbedding whereDocumentVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSearchEmbedding whereEmbedding($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSearchEmbedding whereTitleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSearchEmbedding whereUpdatedAt($value)
 */
	class TitleSearchEmbedding extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $title_id
 * @property int $similar_title_id
 * @property float $score
 * @property list<array{type: string, values: array<int, string>, weight: float}> $reasons
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Title $title
 * @property-read Title $similarTitle
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereReasons($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereSimilarTitleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereTitleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class TitleSimilarity extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @property string $username
 * @property string|null $google_id
 * @property string|null $google_token
 * @property int|null $country_id
 * @property \Spatie\SchemalessAttributes\SchemalessAttributes|null $extra_attributes
 * @property-read mixed $avatar_url
 * @property-read Country|null $country
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereExtraAttributes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGoogleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGoogleToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 * @property bool $is_admin
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Keyword> $avoidedKeywords
 * @property-read int|null $avoided_keywords_count
 * @property-read \App\Models\UserTitleState|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Title> $likedTitles
 * @property-read int|null $liked_titles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Title> $titles
 * @property-read int|null $titles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Title> $toWatchTitles
 * @property-read int|null $to_watch_titles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Title> $watchedTitles
 * @property-read int|null $watched_titles_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsAdmin($value)
 * @mixin \Eloquent
 */
	class User extends \Eloquent implements \Illuminate\Contracts\Auth\MustVerifyEmail {}
}

namespace App\Models{
/**
 * @property int $user_id
 * @property int $title_id
 * @property float|null $rate
 * @property bool $liked
 * @property bool $watched
 * @property bool $to_watch
 * @property Carbon $updated_at
 * @property-read Title $title
 * @property-read User $user
 * @method static \Database\Factories\UserTitleStateFactory factory($count = null, $state = [])
 * @method static Builder<static>|UserTitleState newModelQuery()
 * @method static Builder<static>|UserTitleState newQuery()
 * @method static Builder<static>|UserTitleState query()
 * @method static Builder<static>|UserTitleState whereRate($value)
 * @method static Builder<static>|UserTitleState whereTitleId($value)
 * @method static Builder<static>|UserTitleState whereToWatch($value)
 * @method static Builder<static>|UserTitleState whereUpdatedAt($value)
 * @method static Builder<static>|UserTitleState whereUserId($value)
 * @method static Builder<static>|UserTitleState whereWatched($value)
 * @property int $id
 * @property string $embedding_model
 * @property Carbon|null $created_at
 * @property string $taste
 * @method static Builder<static>|UserTaste whereCreatedAt($value)
 * @method static Builder<static>|UserTaste whereEmbeddingModel($value)
 * @method static Builder<static>|UserTaste whereId($value)
 * @method static Builder<static>|UserTaste whereTaste($value)
 * @mixin \Eloquent
 */
	class UserTaste extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $user_id
 * @property int $title_id
 * @property float|null $rate
 * @property bool $liked
 * @property bool $watched
 * @property bool $to_watch
 * @property Carbon $updated_at
 * @property-read Title $title
 * @property-read User $user
 * @method static \Database\Factories\UserTitleStateFactory factory($count = null, $state = [])
 * @method static Builder<static>|UserTitleState newModelQuery()
 * @method static Builder<static>|UserTitleState newQuery()
 * @method static Builder<static>|UserTitleState query()
 * @method static Builder<static>|UserTitleState whereRate($value)
 * @method static Builder<static>|UserTitleState whereTitleId($value)
 * @method static Builder<static>|UserTitleState whereToWatch($value)
 * @method static Builder<static>|UserTitleState whereUpdatedAt($value)
 * @method static Builder<static>|UserTitleState whereUserId($value)
 * @method static Builder<static>|UserTitleState whereWatched($value)
 * @property int $id
 * @method static Builder<static>|UserTitleState whereId($value)
 * @method static Builder<static>|UserTitleState whereLiked($value)
 * @mixin \Eloquent
 */
	class UserTitleState extends \Eloquent {}
}

