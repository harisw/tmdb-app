<?php

namespace App\Models;

use Database\Factories\TitleSearchEmbeddingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $title_id
 * @property string $embedding
 * @property int $document_version
 * @property-read Title $title
 */
class TitleSearchEmbedding extends Model
{
    /** @use HasFactory<TitleSearchEmbeddingFactory> */
    use HasFactory;

    public const DIMENSIONS = 512;

    protected $primaryKey = 'title_id';

    public $incrementing = false;

    protected $guarded = [];

    protected $hidden = ['embedding'];

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    /**
     * @param  array<int, float>  $vector
     */
    public static function toVectorLiteral(array $vector): string
    {
        return '['.implode(',', $vector).']';
    }
}
