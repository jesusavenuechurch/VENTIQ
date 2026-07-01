<?php

return [

    'default' => env('AI_PROVIDER', 'ollama'),

    'ollama' => [
        'url'         => env('OLLAMA_URL', 'http://localhost:11434'),
        'model'       => env('OLLAMA_MODEL', 'qwen2.5:7b'),
        'timeout'     => env('OLLAMA_TIMEOUT', 300),
        'temperature' => env('OLLAMA_TEMPERATURE', 0.7),
    ],

    'openrouter' => [
        'api_key'     => env('OPENROUTER_API_KEY'),
        'base_url'    => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'model'       => env('OPENROUTER_MODEL', 'meta-llama/llama-3.3-70b-instruct:free'),
        'max_tokens'  => env('OPENROUTER_MAX_TOKENS', 1024),
        'temperature' => env('OPENROUTER_TEMPERATURE', 0.7),
    ],

];