<?php

namespace App\Domain\Casts;

use App\Domain\Enums\CreditJob;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Maps TMDB's raw `job` strings (crew) onto our internal CreditJob taxonomy.
 *
 * TMDB has ~700 distinct job titles across departments. Most are noise for a
 * similarity graph — stunt doubles, assistant accountants, third assistant
 * directors, etc. This cast is intentionally a denylist-by-omission: only
 * jobs that are meaningful signal for "titles a user might like because of
 * this person" are mapped. Anything else resolves to null, which the import
 * service treats as "skip this credit."
 *
 * Producer tiers are deliberately narrow (Producer / Executive Producer only)
 * — Co-Producer, Line Producer, Consulting Producer, Associate Producer are
 * high-fanout, low-signal credits that dilute similarity edges more than
 * they help.
 *
 * Art Direction includes Production Design + Supervising Art Director + plain
 * Art Direction. Expect several people per title (TMDB lists it per
 * season/unit for TV) — filter by episode_count in the import service rather
 * than trying to exclude it here.
 */
class TmdbCreditJobCast implements Cast
{
    private const JOB_MAP = [
        // Directing
        'Director' => CreditJob::DIRECTOR,
        'Co-Director' => CreditJob::DIRECTOR,
        'Series Director' => CreditJob::DIRECTOR,

        // Sound — composer
        'Original Music Composer' => CreditJob::COMPOSER,
        'Main Title Theme Composer' => CreditJob::COMPOSER,

        // Writing
        'Writer' => CreditJob::WRITER,
        'Screenplay' => CreditJob::WRITER,
        'Teleplay' => CreditJob::WRITER,
        'Story' => CreditJob::WRITER,
        'Novel' => CreditJob::WRITER,
        'Characters' => CreditJob::WRITER,
        'Original Story' => CreditJob::WRITER,
        'Author' => CreditJob::WRITER,
        'Co-Writer' => CreditJob::WRITER,
        'Original Series Creator' => CreditJob::WRITER,
        'Creator' => CreditJob::WRITER,

        // Art — visual style signal
        'Production Design' => CreditJob::ART_DIRECTOR,
        'Art Direction' => CreditJob::ART_DIRECTOR,
        'Supervising Art Director' => CreditJob::ART_DIRECTOR,

        // Production — top tier only, see class docblock
        'Producer' => CreditJob::PRODUCER,
        'Executive Producer' => CreditJob::PRODUCER,

        // Occasionally TMDB credits an actor inside crew[] instead of
        // cast[] (archive footage, uncredited cameo listed oddly, etc.)
        'Actor' => CreditJob::CAST,
        'Voice' => CreditJob::CAST,
        'Special Guest' => CreditJob::CAST,
        'Cameo' => CreditJob::CAST,
    ];

    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if ($value instanceof CreditJob) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        // Already a canonical enum value (e.g. the 'cast' sentinel the
        // normalizer assigns to cast[] rows, or a re-hydrated record) —
        // resolve it directly instead of running it through the job map.
        if ($enum = CreditJob::tryFrom($value)) {
            return $enum;
        }

        // Otherwise this is a raw TMDB `job` string from crew[].
        return self::JOB_MAP[$value] ?? null;
    }

    /**
     * Non-Data-context helper — useful when you want to check/skip a job
     * before ever building a CreditData instance (see the normalizer).
     */
    public static function resolve(string $tmdbJob): ?CreditJob
    {
        if ($enum = CreditJob::tryFrom($tmdbJob)) {
            return $enum;
        }

        return self::JOB_MAP[$tmdbJob] ?? null;
    }

    public static function map(): array
    {
        return self::JOB_MAP;
    }
}
