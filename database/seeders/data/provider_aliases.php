<?php

use App\Domain\Enums\ProviderSource;

return [
    'tmdb_variants' => [
        2100 => 119, // Amazon Prime Video with Ads → Amazon Prime Video
        10 => 119, // Amazon Video
        613 => 119, // Amazon Prime Video Free with Ads
        178 => 1856, // MagentaTV → Magenta TV
        64 => 63, // Filmin Plus → Filmin
        387 => 386, // Peacock Premium Plus → Peacock Premium
        2102 => 381, // Premiery Canal+ → Canal+
        58 => 381, // Canal VOD
        149 => 2241, // Movistar Plus+ Ficción Total → Movistar Plus+
        339 => 2241, // MovistarTV
        2142 => 34, // MGM+ Apple TV Channel → MGM Plus
        2141 => 34, // MGM Plus Amazon Channel
        636 => 34, // MGM Plus Roku Premium Channel
        1825 => 1899, // HBO Max Amazon Channel → HBO Max
        2284 => 1899, // HBO Max on U-Next
        591 => 39, // Now TV Cinema → Now TV
        1855 => 43, // Starz Apple TV channel → Starz
        630 => 43, // STARZPLAY
        1794 => 43, // Starz Amazon Channel
        634 => 43, // Starz Roku Premium Channel
        332 => 7, // Fandango at Home Free → Fandango At Home
        201 => 11, // MUBI Amazon Channel → MUBI
        2243 => 350, // Apple TV Amazon Channel → Apple TV
        1968 => 283, // Crunchyroll Amazon Channel → Crunchyroll
        1898 => 515, // Amazon MX Player → MX Player
        122 => 337, // Disney+ → Disney Plus
        1853 => 531, // Paramount Plus Apple TV channel → Paramount Plus
        2303 => 531, // Paramount Plus Premium
        2304 => 531, // Paramount Plus Basic with Ads
        582 => 531, // Paramount+ Amazon Channel
        2616 => 531, // Paramount Plus Essential
        633 => 531, // Paramount+ Roku Premium Channel
        2624 => 1773, // TV2 Skyshowtime → SkyShowtime
        2300 => 41, // ITVX Premium → ITVX
        204 => 99, // Shudder Amazon Channel → Shudder
        2049 => 99, // Shudder Apple TV channel
        528 => 526, // AMC+ Amazon Channel → AMC+
        1854 => 526, // AMC Plus Apple TV channel
        635 => 526, // AMC+ Roku Premium Channel
        2660 => 30, // Wow Fiction Amazon Channel → WOW
        2053 => 561, // Lionsgate Play Apple TV Channel → Lionsgate Play
        2074 => 561, // Lionsgate Play Amazon Channel
        2358 => 561, // Lionsgate+ Amazon Channel
        1866 => 457, // ViX Premium Amazon Channel → VIX
        572 => 2750, // RTL Play → RTL+
        2578 => 2750, // RTL+ Max Amazon Channel
        421 => 304, // Joyn Plus → Joyn
        285 => 38, // BBC Player Amazon Channel → BBC iPlayer
        1852 => 151, // Britbox Apple TV channel → BritBox
        197 => 151, // BritBox Amazon Channel
    ],

    'external' => [
        ProviderSource::WATCHMODE->value => [
            'Amazon' => 10,
            'AppleTV' => 2,
            'Fandango at Home' => 7,
            'Rakuten TV' => 35,
            'maxdome Store' => 20,
            'Sky Store' => 130,
            'Peacock' => 386,
            'Peacock Premium' => 386,
        ],
        ProviderSource::STREAMING_AVAILABILITY->value => [
            'netflix' => 8,
            'prime' => 119,
            'disney' => 337,
            'apple' => 350,
            'hbo' => 1899,
            'crunchyroll' => 283,
            'crave' => 230,
            'hulu' => 15,
            'roku' => 207,
            'starz' => 43,
            'paramount' => 531,
            'peacock' => 386,
        ],
    ],
];
