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
    | HTTP Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Maximum time to wait for a response from the AI provider.
    |
    */

    'timeout' => (int) env('AI_TIMEOUT', 30),

];