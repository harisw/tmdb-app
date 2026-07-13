<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

/**
 * How cast is ranked before it's trimmed to the top N.
 *
 * TMDB's two credit shapes carry different signals. /credits gives a billing
 * `order` (0 = top-billed). /aggregate_credits has no meaningful series-wide
 * billing order, so the useful signal is how many episodes the person actually
 * appeared in.
 */
enum CastRanking: string
{
    use EnumValues;

    case ORDER = 'order';
    case EPISODE_COUNT = 'episode_count';
}
