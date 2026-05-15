<?php

namespace App\Models;

use App\Domain\Enums\ProviderLanguageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $title_provider_id
 * @property int $language_id
 * @property ProviderLanguageType $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Language $language
 * @property-read TitleProvider $titleProvider
 *
 * @method static \Database\Factories\TitleProviderLanguageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage whereLanguageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage whereTitleProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TitleProviderLanguage whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class TitleProviderLanguage extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'type' => ProviderLanguageType::class,
    ];

    public function titleProvider(): BelongsTo
    {
        return $this->belongsTo(TitleProvider::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
