<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 *
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property float $rating
 * @property string $status
 * @property Carbon $release_date
 * @property int $revenue
 * @property int $runtime
 * @property string|null $img_backdrop
 * @property string|null $imdb_id
 * @property string|null $overview
 * @property float $popularity
 * @property string|null $img_poster
 * @property string|null $tagline
 * @property int $language_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductionCompany> $companies
 * @property-read int|null $companies_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Genre> $genres
 * @property-read int|null $genres_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Keyword> $keywords
 * @property-read int|null $keywords_count
 * @method static \Database\Factories\MovieFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereCreatedAt($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Movie whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Movie extends Model
{
    /** @use HasFactory<\Database\Factories\MovieFactory> */
    use HasFactory;

    public const IMG_SMALL_URL = 'w300';
    public const IMG_MEDIUM_URL = 'w780';
    public const IMG_LARGE_URL = 'w1280';

    protected $casts = [
        'runtime' => 'string',
        'release_date' => 'datetime:Y'
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
