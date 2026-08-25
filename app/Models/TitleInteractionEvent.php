<?php

namespace App\Models;

use App\Domain\Enums\InteractionEventType;
use Database\Factories\TitleInteractionEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $title_id
 * @property InteractionEventType $event_type
 * @property numeric|null $value
 * @property Carbon $created_at
 * @property-read Title $title
 * @property-read User $user
 *
 * @method static \Database\Factories\TitleInteractionEventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent whereTitleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleInteractionEvent whereValue($value)
 *
 * @mixin \Eloquent
 */
class TitleInteractionEvent extends Model
{
    /** @use HasFactory<TitleInteractionEventFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'title_id',
        'event_type',
        'value',
    ];

    protected $casts = [
        'event_type' => InteractionEventType::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }
}
