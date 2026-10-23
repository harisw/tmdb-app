<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $title_id
 * @property int $similar_title_id
 * @property float $score
 * @property list<array{type: string, values: array<int, string>, weight: float}> $reasons
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Title $title
 * @property-read Title $similarTitle
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereReasons($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereSimilarTitleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereTitleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleSimilarity whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class TitleSimilarity extends Pivot
{
    protected $table = 'title_similarities';

    public $incrementing = true;

    protected $fillable = [
        'title_id',
        'similar_title_id',
        'score',
        'reasons',
    ];

    protected $casts = [
        'score' => 'float',
        'reasons' => 'array',
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
