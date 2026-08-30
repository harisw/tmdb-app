<?php

namespace App\Models;

use App\Casts\RoundFloat;
use Database\Factories\UserTitleStateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property int $title_id
 * @property float|null $rate
 * @property bool $liked
 * @property bool $watched
 * @property bool $to_watch
 * @property Carbon $updated_at
 * @property-read Title $title
 * @property-read User $user
 *
 * @method static \Database\Factories\UserTitleStateFactory factory($count = null, $state = [])
 * @method static Builder<static>|UserTitleState newModelQuery()
 * @method static Builder<static>|UserTitleState newQuery()
 * @method static Builder<static>|UserTitleState query()
 * @method static Builder<static>|UserTitleState whereRate($value)
 * @method static Builder<static>|UserTitleState whereTitleId($value)
 * @method static Builder<static>|UserTitleState whereToWatch($value)
 * @method static Builder<static>|UserTitleState whereUpdatedAt($value)
 * @method static Builder<static>|UserTitleState whereUserId($value)
 * @method static Builder<static>|UserTitleState whereWatched($value)
 *
 * @mixin \Eloquent
 */
class UserTitleState extends Model
{
    /** @use HasFactory<UserTitleStateFactory> */
    use HasFactory;

    const CREATED_AT = null;

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'title_id',
        'liked',
        'watched',
        'to_watch',
        'rate',
    ];

    protected $casts = [
        'liked' => 'boolean',
        'watched' => 'boolean',
        'to_watch' => 'boolean',
        'rate' => RoundFloat::class.':1',
    ];

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query
            ->where('user_id', $this->getAttribute('user_id'))
            ->where('title_id', $this->getAttribute('title_id'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }
}
