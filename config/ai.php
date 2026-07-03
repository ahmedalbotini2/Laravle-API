<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active AI Provider
    |--------------------------------------------------------------------------
    |
    | Supported: "stub", "openai"
    |
    | "stub"   — returns deterministic fake data (no external calls).
    | "openai" — forwards requests to the OpenAI Chat Completions API.
    |
    | You may add more providers by implementing AiProviderInterface and
    | registering them in AiProviderFactory.
    |
    */

    'provider' => env('AI_PROVIDER', 'stub'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | The secret key used to authenticate with the AI provider.
    | Never hard-code this value — always use the .env file.
    |
    */

    'api_key' => env('AI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL (optional override)
    |--------------------------------------------------------------------------
    |
    | Override the default base URL for the provider's API.
    | Useful for proxies, self-hosted models, or Azure OpenAI endpoints.
    |
    */

    'api_url' => env('AI_API_URL'),

    /*
    |--------------------------------------------------------------------------
    | Model Name
    |--------------------------------------------------------------------------
    |
    | The specific model to use (e.g., "gpt-4o", "gemini-pro-vision").
    | Each provider adapter can define its own default if this is null.
    |
    */

    'model' => env('AI_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Model Rotation (optional)
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of models to try in order for each request, e.g.:
    |   AI_MODELS="nvidia/nemotron-nano-12b-v2-vl:free,google/gemma-4-31b-it:free"
    |
    | If a model returns HTTP 429 (quota/rate limit exceeded), the next model
    | in the list is tried immediately for the same request. If this is left
    | empty, the single AI_MODEL above is used, or — if that's empty too —
    | the provider's own built-in default rotation of free models.
    |
    */

    'models' => env('AI_MODELS'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Maximum time to wait for a response from the AI provider.
    |
    */

    'timeout' => (int) env('AI_TIMEOUT', 30),

];