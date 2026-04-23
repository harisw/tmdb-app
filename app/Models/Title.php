<?php

namespace App\Models;

use App\Casts\RoundFloat;
use App\Helpers\DateTimeHelper;
use Database\Factories\TitleFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

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
 *
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
 *
 * @mixin \Eloquent
 * @mixin IdeHelperTitle
 */
class Title extends Model
{
    /** @use HasFactory<TitleFactory> */
    use HasFactory;

    public const IMG_SMALL_URL = 'w300';

    public const IMG_MEDIUM_URL = 'w780';

    public const IMG_LARGE_URL = 'w1280';

    protected $guarded = [];

    protected $casts = [
        'runtime' => 'string',
        'release_date' => 'datetime:Y',
        'rating' => RoundFloat::class,
    ];

    public function getRuntimeAttribute($value): string
    {
        return DateTimeHelper::minutesToHours($value);
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
}
