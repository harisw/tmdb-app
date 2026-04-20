<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $title_id
 * @property string|null $search_vector
 * @property-read Title|null $title
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableTitle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableTitle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableTitle query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableTitle whereTitleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchableTitle whereSearchVector($value)
 *
 * @mixin \Eloquent
 */
class SearchableTitle extends Model
{
    public $timestamps = false;

    protected $table = 'searchable_titles';

    public $incrementing = false;

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class, 'title_id', 'id');
    }
}
