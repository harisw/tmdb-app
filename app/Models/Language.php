<?php

namespace App\Models;

use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $count
 * @property-read Collection<int, Title> $titles
 * @property-read int|null $titles_count
 *
 * @method static \Database\Factories\LanguageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language whereName($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperLanguage
 */
class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'code',
    ];

    public $timestamps = false;

    public function titles(): HasMany
    {
        return $this->hasMany(Title::class);
    }

    public static function findOther(): self
    {
        return self::firstWhere('code', 'other');
    }

    public static function findByCode(string $code): self
    {
        return cache()->remember("language-$code", 60 * 60 * 24, function () use ($code) {
            return self::firstWhere('code', $code);
        }) ?? self::findOther();
    }
}
