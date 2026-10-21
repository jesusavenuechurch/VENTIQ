<?php

return [

    'default' => env('AI_PROVIDER', 'ollama'),

    'ollama' => [
        'url'         => env('OLLAMA_URL', 'http://localhost:11434'),
        'model'       => env('OLLAMA_MODEL', 'qwen2.5:7b'),
        'timeout'     => env('OLLAMA_TIMEOUT', 300),
        'temperature' => env('OLLAMA_TEMPERATURE', 0.7),
        // A smaller, quicker model for the live sticky notes (e.g. qwen2.5:7b); the main one when unset.
        'fast_model'  => env('OLLAMA_FAST_MODEL'),
        // How much prompt the model may take (tokens). Ollama's default is small and
        // drops the start of a longer prompt: 16384 suits Ask Ventiq and long meetings.
        'num_ctx'     => env('OLLAMA_NUM_CTX', 16384),
        // Whether the model takes a thinking level (low/medium/high). Unset: gpt-oss does.
        'thinking'    => env('OLLAMA_THINKING'),
        // For Ask Ventiq's search: run `ollama pull nomic-embed-text` on the Mac mini.
        'embed_model' => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),
        'embed_timeout' => env('OLLAMA_EMBED_TIMEOUT', 60),
    ],

    'openrouter' => [
        'api_key'     => env('OPENROUTER_API_KEY'),
        'base_url'    => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'model'       => env('OPENROUTER_MODEL', 'meta-llama/llama-3.3-70b-instruct:free'),
        'max_tokens'  => env('OPENROUTER_MAX_TOKENS', 1024),
        'temperature' => env('OPENROUTER_TEMPERATURE', 0.7),
    ],

];