<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $movie_id
 * @property string|null $search_vector
 * @property-read Movie|null $movie
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableMovie newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableMovie newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableMovie query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableMovie whereMovieId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableMovie whereSearchVector($value)
 *
 * @mixin \Eloquent
 */
class SearchableMovie extends Model
{
    public $timestamps = false;

    protected $table = 'searchable_movies';

    public $incrementing = false;

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'movie_id', 'id');
    }
}
