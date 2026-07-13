<?php

namespace App\Models;

use App\Domain\Enums\Gender;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Gender $gender
 * @property bool $is_adult
 * @property string|null $profile_path
 * @property float $popularity
 * @property int|null $tmdb_id
 * @property SchemalessAttributes $extra_attributes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Title> $titles
 * @property-read int|null $titles_count
 * @property-read Collection<int, TitlePerson> $credits
 * @property-read int|null $credits_count
 *
 * @method static \Database\Factories\PersonFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereIsAdult($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person wherePopularity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereProfilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereTmdbId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Person whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    protected $table = 'people';

    protected $guarded = [];

    protected $casts = [
        'gender' => Gender::class,
        'is_adult' => 'boolean',
        'popularity' => 'float',
        'extra_attributes' => SchemalessAttributes::class,
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeWithExtraAttributes(): Builder
    {
        return $this->extra_attributes->modelScope();
    }

    public function titles(): BelongsToMany
    {
        return $this->belongsToMany(Title::class, 'title_person')
            ->using(TitlePerson::class)
            ->withPivot(['job', 'character', 'order', 'episode_count'])
            ->withTimestamps();
    }

    public function credits(): HasMany
    {
        return $this->hasMany(TitlePerson::class);
    }
}
