<?php

namespace App\Models;

use Database\Factories\GenreFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

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
 *
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
 *
 * @mixin \Eloquent
 */
class Genre extends Model
{
    /** @use HasFactory<GenreFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['url'];

    /**
     * @return BelongsToMany<Title, $this>
     */
    public function titles(): BelongsToMany
    {
        return $this->belongsToMany(Title::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => route('titles-by-genre', $attributes['slug'])
        );
    }
}
