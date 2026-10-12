<?php

/**
 * Keyword blocklist for SharedKeywordsPipe
 *
 * Three categories — all genuinely meaningless for content similarity:
 *
 *   malformed      : keywords with broken spacing (e.g. "save - the - day")
 *                    — duplicates of valid slugs that will never match cleanly.
 *                    Consider normalising these via str_replace(' - ', '-') instead.
 *
 *   internal_tags   : non-transferable identifiers tied to one specific show/film.
 *                    Zero signal for any other title.
 *
 *   raw_years       : bare year numbers with no context (e.g. "1984", "2019")
 *                    — decade slugs like "1970s" are kept as they carry era signal.
 */

return [

    '10th - century',
    '12th - century',
    '15th - century',
    '16th - century',
    '17th - century',
    '18th - century',
    '19th - century',
    '1st - century',
    '20th - century',
    '21st - century',
    '23rd - century',
    '24th - century',
    '2nd - century',
    '40 - something',
    '4th - of - july',
    '80s - style',
    '90s - style',
    'abandoned - building',
    'abandoned - house',
    'abandoned - theater',
    'abandoned - warehouse',
    'abusive - father',
    'abusive - husband',
    'abusive - mother',
    'abusive - relationship',
    'action - comedy',
    'action - packed',
    'actor - playing - themself',
    'adopted - child',
    'adopted - son',
    'adoptive - father',
    'adult - animation',
    'african - american - history',
    'age - gap - relationship',
    'air - controller',
    'air - traffic - control',
    'alcohol - abuse',
    'alcoholic - father',
    'alien - abduction',
    'alien - attack',
    'alien - friendship',
    'animal - attack',
    'bank - heist',
    'based - on - short - film',
    'big - family',
    'black - and - white',
    'candy - bar',
    'car - crash',
    'casual - sex',
    'child - abuse',
    'cold - war',
    'coming - of - age',
    'erotic - thriller',
    'factory - worker',
    'female - stripping',
    'fired - from - the - job',
    'foot - fetish',
    'house - fire',
    'identity - swap',
    'illegal - drugs',
    'inanimate - objects - come - to - life',
    'internet - porn',
    'iraq - war',
    'irish - american',
    'lab - animal',
    'lifes - dream',
    'music - school',
    'music - teacher',
    'overweight - child',
    'pizza - boy',
    'police - training',
    'save - the - day',
    'solar - eclipse',
    'state - police',
    'two - worlds',
    'us - marine',
    'us - navy - seal',

    // common words
    'sequel',
    // -------------------------------------------------------------------------
    // Raw years (no context)
    // Bare year numbers add noise — any two films from 2019 would get bumped.
    // Decade slugs like "1970s", "1980s" are kept — they carry era signal.
    // -------------------------------------------------------------------------
    '1767', '1836', '1912', '1918', '1927', '1945',
    '1958', '1968', '1969', '1975', '1983', '1984',
    '1986', '1989', '2001', '2002', '2008', '2009',
    '2013', '2018', '2019', '2020', '2025', '3001',

];
