<?php

return [

    'openai' => [
        'model' => env('OPENAI_LLM_MODEL', ''),
        'key' => env('OPENAI_KEY', ''),
    ],

    'groq' => [
        'model' => env('GROQ_LLM_MODEL', ''),
        'key' => env('GROQ_KEY', ''),
    ],
];
