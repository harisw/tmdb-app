<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'tmdb' => [
        'base_url' => env('TMDB_BASE_URL'),
        'api_key' => env('TMDB_API_KEY'),
        'img_url' => env('MOVIE_DB_IMG_URL'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => 'https://api.openai.com/v1',
        'llm_model' => env('OPENAI_LLM_MODEL') ?: 'gpt-4.1-mini',
        'llm_parameters' => array_filter(['reasoning_effort' => env('OPENAI_LLM_REASONING_EFFORT', '')]),
    ],
    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'base_url' => 'https://api.groq.com/openai/v1',
        'llm_model' => env('GROQ_LLM_MODEL') ?: 'openai/gpt-oss-120b',
        'llm_parameters' => array_filter(['reasoning_effort' => env('GROQ_LLM_REASONING_EFFORT', 'low')]),
    ],
    'llm' => [
        'providers' => explode(',', env('LLM_PROVIDERS', 'openai,groq')),
        'timeout' => (int) env('LLM_TIMEOUT', 10),
        'cooldown' => (int) env('LLM_COOLDOWN', 60),
    ],
    'langfuse' => [
        'base_url' => env('LANGFUSE_BASE_URL', 'https://cloud.langfuse.com'),
        'public_key' => env('LANGFUSE_PUBLIC_KEY'),
        'secret_key' => env('LANGFUSE_SECRET_KEY'),
    ],
    'providers' => [
        'movie_night' => [
            'api_key' => env('MOVIE_NIGHT_API_KEY'),
            'base_url' => env('MOVIE_NIGHT_BASE_URL'),
        ],
        'watchmode' => [
            'api_key' => env('WATCHMODE_API_KEY'),
            'base_url' => env('WATCHMODE_BASE_URL'),
        ],
    ],
    'avatar' => [
        'base_url' => env('AVATAR_BASE_URL', 'https://api.dicebear.com/10.x/'),
    ],
    'seed' => [
        'csv_local' => env('SEED_CSV_LOCAL'),
        'csv_s3_key' => env('SEED_CSV_S3_KEY'),
    ],
];
