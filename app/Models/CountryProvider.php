<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\SchemalessAttributes\SchemalessAttributes;

/**
 * @mixin IdeHelperCountryProvider
 */
class CountryProvider extends Model
{
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
