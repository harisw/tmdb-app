<?php

namespace App\Models;

use App\Domain\Enums\StreamType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $country_provider
 * @property int $country_id
 * @property int $provider_id
 * @property int $title_id
 * @property StreamType $type
 * @property int $priority
 * @property array<array-key, mixed> $audio_languages
 * @property array<array-key, mixed> $subtitle_languages
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Country $country
 * @property-read CountryProvider $countryProvider
 * @property-read Provider $provider
 * @property-read Collection<int, TitleProviderLanguage> $providerLanguages
 * @property-read int|null $provider_languages_count
 * @property-read Title $title
 *
 * @method static \Database\Factories\TitleProviderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereAudioLanguages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereCountryProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereSubtitleLanguages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereTitleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProvider whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class TitleProvider extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'type' => StreamType::class,
        'audio_languages' => 'array',
        'subtitle_languages' => 'array',
    ];

    public function countryProvider(): BelongsTo
    {
        return $this->belongsTo(CountryProvider::class, 'country_provider_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function providerLanguages(): HasMany
    {
        return $this->hasMany(TitleProviderLanguage::class);
    }
}
