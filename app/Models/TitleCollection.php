<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $tmdb_id
 * @property string $slug
 * @property string $name
 * @property string|null $poster_path
 * @property string|null $backdrop_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
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
 *
 * @mixin \Eloquent
 */
class TitleCollection extends Model
{
    //
}
