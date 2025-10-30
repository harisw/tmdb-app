<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @mixin \Eloquent
 */
class SearchableMovie extends Model
{

    public $timestamps = false;
    protected $table = 'searchable_movies';
    public $incrementing = false;

    /**
     * @return BelongsTo
     */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'movie_id', 'id');
    }
}
