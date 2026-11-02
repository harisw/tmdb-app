<?php

namespace App\Models;

use App\Domain\Enums\ProviderSource;
use Database\Factories\ProviderAliasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $provider_id
 * @property ProviderSource $source
 * @property string $external_id
 * @property string|null $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Provider $provider
 *
 * @method static \Database\Factories\ProviderAliasFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class ProviderAlias extends Model
{
    /** @use HasFactory<ProviderAliasFactory> */
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'source',
        'external_id',
        'name',
    ];

    protected $casts = [
        'provider_id' => 'integer',
        'source' => ProviderSource::class,
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
