<?php

namespace App\Models;

use App\Domain\Enums\StreamType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperTitleProvider
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
        return $this->belongsTo(CountryProvider::class, 'country_provider');
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
