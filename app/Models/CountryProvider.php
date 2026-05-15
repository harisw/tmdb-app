<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\SchemalessAttributes\SchemalessAttributes;

/**
 * @property int $id
 * @property int $country_id
 * @property int $provider_id
 * @property int $priority
 * @property SchemalessAttributes|null $extra_attributes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Country $country
 * @property-read Provider $provider
 *
 * @method static \Database\Factories\CountryProviderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider whereExtraAttributes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CountryProvider whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class CountryProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'provider_id',
        'priority',
        'extra_attributes',
    ];

    protected $casts = [
        'country_id' => 'integer',
        'provider_id' => 'integer',
        'priority' => 'integer',
        'extra_attributes' => SchemalessAttributes::class,
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
