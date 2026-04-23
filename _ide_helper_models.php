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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Language|null $language
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
	#[\AllowDynamicProperties]
	class IdeHelperCountry {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $country_id
 * @property int $provider_id
 * @property int $priority
 * @property \Spatie\SchemalessAttributes\SchemalessAttributes|null $extra_attributes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Country $country
 * @property-read \App\Models\Provider $provider
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
	#[\AllowDynamicProperties]
	class IdeHelperCountryProvider {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $count
 * @property-read Collection<int, Title> $titles
 * @property-read int|null $titles_count
 * @method static \Database\Factories\GenreFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Genre whereSlug($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperGenre {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $count
 * @property-read Collection<int, Title> $titles
 * @property-read int|null $titles_count
 * @method static \Database\Factories\KeywordFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Keyword whereSlug($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperKeyword {}
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
	#[\AllowDynamicProperties]
	class IdeHelperLanguage {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $count
 * @property-read Collection<int, Title> $titles
 * @property-read int|null $titles_count
 * @method static \Database\Factories\ProductionCompanyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereSlug($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperProductionCompany {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $tmdb_provider_id
 * @property string $slug
 * @property string $name
 * @property string|null $img_logo
 * @property int $priority
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Country> $countries
 * @property-read int|null $countries_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CountryProvider> $countryProviders
 * @property-read int|null $country_providers_count
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
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperProvider {}
}

namespace App\Models{
/**
 * @property int|null $title_id
 * @property string|null $search_vector
 * @property-read Title|null $title
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableTitle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableTitle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableTitle query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableTitle whereTitleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableTitle whereSearchVector($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperSearchableTitle {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property mixed $rating
 * @property string $status
 * @property Carbon $release_date
 * @property int $revenue
 * @property string $runtime
 * @property string|null $img_backdrop
 * @property string|null $imdb_id
 * @property string|null $overview
 * @property float $popularity
 * @property string|null $img_poster
 * @property string|null $tagline
 * @property int $language_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $tmdb_id
 * @property string|null $homepage
 * @property-read Collection<int, ProductionCompany> $companies
 * @property-read int|null $companies_count
 * @property-read Collection<int, Genre> $genres
 * @property-read int|null $genres_count
 * @property-read Collection<int, Keyword> $keywords
 * @property-read int|null $keywords_count
 * @method static \Database\Factories\TitleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereHomepage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereImdbId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereImgBackdrop($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereImgPoster($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereLanguageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereOverview($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title wherePopularity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereReleaseDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereRuntime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereTagline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereTmdbId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereUpdatedAt($value)
 * @property string $name
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Title whereName($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTitle {}
}

namespace App\Models{
/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTitleProvider {}
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
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUser {}
}

