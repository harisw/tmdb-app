<?php

namespace App\Models;

use Database\Factories\KeywordFactory;
use Illuminate\Database\Eloquent\Builder;
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
 *
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
 *
 * @property-read mixed $url
 *
 * @mixin \Eloquent
 */
class Keyword extends Model
{
    /** @use HasFactory<KeywordFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['url'];

    public function scopeMatching(Builder $query, string $term): Builder
    {
        $escaped = addcslashes($term, '%_\\');

        return $query->where('count', '>', 0)
            ->whereLike('name', "%{$escaped}%")
            ->orderByRaw('name ilike ? desc', ["{$escaped}%"])
            ->orderByDesc('count');
    }

    public function titles(): BelongsToMany
    {
        return $this->belongsToMany(Title::class);
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => route('titles-by-keyword', ['keyword' => $attributes['slug']]),
        );
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
