<?php

namespace App\Models;

use App\Domain\Enums\CreditRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $title_id
 * @property int $person_id
 * @property CreditRole $role
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

    protected $casts = [
        'role' => CreditRole::class,
        'order' => 'integer',
    ];

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function scopeCast(Builder $query): Builder
    {
        return $query->where('role', CreditRole::CAST);
    }

    public function scopeCrew(Builder $query): Builder
    {
        return $query->where('role', CreditRole::CREW);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }
}
