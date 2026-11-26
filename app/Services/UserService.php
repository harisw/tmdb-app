<?php

namespace App\Services;

use App\Data\DTOs\GoogleUserData;
use App\Data\DTOs\RegisterUserData;
use App\Models\Country;
use App\Models\User;
use App\Models\UserTaste;
use App\Models\UserTitleState;
use App\Services\KnowledgeGraph\OpenAIEmbeddingService;
use App\Utils\Helpers\GenerateUsername;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    private const TASTE_SOURCE_LIMIT = 200;

    private const MIN_TASTE_WEIGHT = 2.0;

    public function __construct(private GenerateUsername $usernameGenerator) {}

    public function create(RegisterUserData $data): User
    {
        return User::create([
            ...$this->buildBaseAttributes($this->getUniqueUsername()),
            'name' => explode('@', $data->email)[0],
            'email' => $data->email,
            'password' => Hash::make($data->password),
            'country_id' => Country::where('iso2', $data->country_iso2)->first()->id,
        ]);
    }

    public function createFromGoogle(GoogleUserData $data): User
    {
        return User::forceCreate([
            ...$this->buildBaseAttributes($this->getUniqueUsername()),
            'name' => $data->name,
            'email' => $data->email,
            'google_id' => $data->google_id,
            'google_token' => $data->google_token,
            'email_verified_at' => now(),
        ]);
    }

    public function linkGoogleAccount(User $user, GoogleUserData $data): User
    {
        $user->forceFill([
            'google_id' => $data->google_id,
            'google_token' => $data->google_token,
        ]);

        if (! $user->hasVerifiedEmail() && Str::lower($user->email) === Str::lower($data->email)) {
            $user->forceFill([
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => now(),
            ]);
        }

        $user->save();

        return $user;
    }

    public function findByGoogleId(string $googleId): ?User
    {
        return User::where('google_id', $googleId)->first();
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function computeUserTaste(User $user): ?UserTaste
    {
        $states = $user->titleStates()
            ->join('titles', 'titles.id', '=', 'user_title_states.title_id')
            ->whereNotNull('titles.embedding')
            ->latest('user_title_states.updated_at')
            ->limit(self::TASTE_SOURCE_LIMIT)
            ->get(['user_title_states.*', 'titles.embedding']);

        ['sum' => $sum, 'totalWeight' => $totalWeight] = $this->weightedEmbeddingSum($states);

        if ($totalWeight < self::MIN_TASTE_WEIGHT) {
            UserTaste::whereKey($user->id)->delete();

            return null;
        }

        return UserTaste::updateOrCreate(['user_id' => $user->id], [
            'embedding_model' => OpenAIEmbeddingService::TITLE_EMBEDDING_MODEL,
            'taste' => OpenAIEmbeddingService::vectorArrayToLiteral(
                array_map(fn (float $value) => $value / $totalWeight, $sum)
            ),
        ]);
    }

    /**
     * @param  Collection<int, UserTitleState>  $states
     * @return array{sum: list<float>, totalWeight: float}
     */
    private function weightedEmbeddingSum(Collection $states): array
    {
        $sum = [];
        $totalWeight = 0.0;

        foreach ($states as $state) {
            $weight = $this->tasteWeight($state);

            if ($weight <= 0) {
                continue;
            }

            /** @var list<float> $vector */
            $vector = json_decode($state->getAttribute('embedding'), true);

            foreach ($vector as $i => $value) {
                $sum[$i] = ($sum[$i] ?? 0.0) + $value * $weight;
            }

            $totalWeight += $weight;
        }

        return ['sum' => $sum, 'totalWeight' => $totalWeight];
    }

    private function tasteWeight(UserTitleState $state): float
    {
        return match (true) {
            $state->liked => 1.0,
            $state->rate !== null && $state->rate >= 6 => $state->rate / 10,
            $state->watched && $state->rate === null => 0.5,
            $state->to_watch => 0.3,
            default => 0.0,
        };
    }

    /**
     * @return array{username: string, extra_attributes: array{avatar: array{seed: string, style: string}}}
     */
    private function buildBaseAttributes(string $username): array
    {
        return [
            'username' => $username,
            'extra_attributes' => [
                'avatar' => [
                    'seed' => $username,
                    'style' => AvatarService::getRandomStyle(),
                ],
            ],
        ];
    }

    private function getUniqueUsername(): string
    {
        // The generator draws from a fixed adjective/noun list, so on a fresh
        // database it could otherwise hand a new signup the reserved admin name
        // by chance.
        $reserved = array_filter([config('app.admin_username')]);

        do {
            $newUsername = ($this->usernameGenerator)();
        } while (in_array($newUsername, $reserved, true) || User::where('username', $newUsername)->exists());

        return $newUsername;
    }
}
