<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCountry
 */
class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'iso2',
        'iso3',
        'language_id',
    ];

    protected $casts = [
        'name' => 'string',
        'iso2' => 'string',
        'iso3' => 'string',
        'language_id' => 'integer',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
