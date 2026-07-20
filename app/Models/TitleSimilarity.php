<?php

namespace App\Models;

use App\Domain\Enums\CreditJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $title_id
 * @property int $person_id
 * @property CreditJob $job
 * @property string|null $character
 * @property int $order
 * @property int|null $episode_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Title $title
 * @property-read Person $person
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson whereCharacter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson whereJob($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson wherePersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitlePerson whereTitleId($value)
 *
 * @property int $similar_title_id
 * @property float $score
 * @property string $reasons
 * @property-read Title $similarTitle
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereReasons($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereSimilarTitleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class TitleSimilarity extends Pivot
{
    protected $table = 'title_similarities';

    public $incrementing = true;

    protected $guarded = [];

    protected $fillable = [
        'title_id',
        'similar_title_id',
        'score',
        'reasons',
    ];

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function similarTitle(): BelongsTo
    {
        return $this->belongsTo(Title::class, 'similar_title_id', 'id');
    }
}
