<?php

namespace App\Models;

use App\Casts\RoundFloat;
use App\Helpers\DateTimeHelper;
use Database\Factories\MovieFactory;
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
 * @method static \Database\Factories\MovieFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereHomepage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereImdbId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereImgBackdrop($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereImgPoster($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereLanguageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereOverview($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie wherePopularity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereReleaseDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereRuntime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereTagline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereTmdbId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Movie extends Model
{
    /** @use HasFactory<MovieFactory> */
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
        return $this->belongsToMany(ProductionCompany::class);
    }

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class);
    }
}
