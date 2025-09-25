<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 *
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Movie> $movies
 * @property-read int|null $movies_count
 * @method static \Database\Factories\ProductionCompanyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereSlug($value)
 * @mixin \Eloquent
 */
class ProductionCompany extends Model
{
    /** @use HasFactory<\Database\Factories\ProductionCompanyFactory> */
    use HasFactory;

    public $timestamps = false;

    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class);
    }
}
