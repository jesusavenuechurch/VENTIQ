<?php

return [

    'default' => env('AI_PROVIDER', 'ollama'),

    'ollama' => [
        'url'         => env('OLLAMA_URL', 'http://localhost:11434'),
        'model'       => env('OLLAMA_MODEL', 'qwen2.5:7b'),
        'timeout'     => env('OLLAMA_TIMEOUT', 300),
        'temperature' => env('OLLAMA_TEMPERATURE', 0.7),
    ],

];