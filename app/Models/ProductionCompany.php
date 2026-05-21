<?php

namespace App\Models;

use Database\Factories\ProductionCompanyFactory;
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
 * @method static \Database\Factories\ProductionCompanyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductionCompany whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ProductionCompany extends Model
{
    /** @use HasFactory<ProductionCompanyFactory> */
    use HasFactory;

    protected $guarded = [];

    public function titles(): BelongsToMany
    {
        return $this->belongsToMany(Title::class);
    }
}
