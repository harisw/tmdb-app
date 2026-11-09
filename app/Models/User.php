<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Scopes\AvoidedKeywordScope;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 *
 * @property string $username
 * @property string|null $google_id
 * @property string|null $google_token
 * @property int|null $country_id
 * @property \Spatie\SchemalessAttributes\SchemalessAttributes|null $extra_attributes
 * @property-read mixed $avatar_url
 * @property-read Country|null $country
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereExtraAttributes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGoogleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGoogleToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const MAX_AVOIDED_KEYWORDS = 50;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'google_id',
        'google_token',
        'image',
        'country_id',
        'extra_attributes',
        // 'is_admin' is deliberately absent: it must never be mass-assignable
        // from request input. Grant it with `php artisan admin:grant`.
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['avatar_url'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'extra_attributes' => SchemalessAttributes::class,
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    protected function avatarUrl(): Attribute
    {
        $avatar = $this->extra_attributes['avatar'];

        // If using the HTTP API:
        return Attribute::make(
            get: fn () => config('services.avatar.base_url')."{$avatar['style']}/svg?seed=".urlencode($avatar['seed'])
        );
    }

    /**
     * @return BelongsToMany<Title, $this, UserTitleState>
     */
    public function titles(): BelongsToMany
    {
        return $this->belongsToMany(Title::class, 'user_title_states')
            ->using(UserTitleState::class)
            ->withPivot('id', 'liked', 'watched', 'to_watch', 'rate')
            ->withoutGlobalScope(AvoidedKeywordScope::class);
    }

    public function avoidedKeywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class, 'user_avoided_keywords')->withTimestamps();
    }

    /**
     * @return list<int>
     */
    public function avoidedKeywordIds(): array
    {
        return once(fn () => $this->avoidedKeywords()->pluck('keywords.id')->all());
    }

    public function likedTitles(): BelongsToMany
    {
        return $this->titles()->wherePivot('liked', true);
    }

    public function watchedTitles(): BelongsToMany
    {
        return $this->titles()->wherePivot('watched', true);
    }

    public function toWatchTitles(): BelongsToMany
    {
        return $this->titles()->wherePivot('to_watch', true);
    }
}
