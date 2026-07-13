<?php

namespace App\Models;

use App\Domain\Enums\CreditJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $title_id
 * @property int $person_id
 * @property CreditJob $role
 * @property string|null $character
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Title $title
 * @property-read Person $person
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson whereCharacter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson wherePersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson whereTitleId($value)
 *
 * @mixin \Eloquent
 */
class TitlePerson extends Pivot
{
    protected $table = 'title_person';

    public $incrementing = true;

    protected $guarded = [];

    protected $fillable = [
        'title_id',
        'person_id',
        'job',
        'character',
        'order',
        'episode_count',
    ];

    protected $casts = [
        'job' => CreditJob::class,
        'order' => 'integer',
        'episode_count' => 'integer',
    ];

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }
}
