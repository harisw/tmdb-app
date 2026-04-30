<?php

namespace App\Models;

use App\Domain\Enums\ProviderLanguageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTitleProvider
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
