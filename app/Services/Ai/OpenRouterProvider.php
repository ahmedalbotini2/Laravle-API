<?php

namespace App\Services\Ai;

use App\Contracts\AiProviderInterface;
use App\Exceptions\AiProviderException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenRouter-based AI provider.
 *
 * Sends the image + prompt to the OpenRouter Chat Completions API and parses
 * the response into the standardised {percentage, text} format.
 *
 * OpenRouter is compatible with the OpenAI Chat Completions API but routes
 * requests to multiple model providers. Configure the model string using the
 * provider/model-name format (e.g. "openai/gpt-4o", "anthropic/claude-3.5-sonnet").
 *
 * @see https://openrouter.ai/docs
 */
class OpenRouterProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $baseUrl;
    /** @var string[] Ordered list of models to try, in rotation, for a single request. */
    private array  $models;
    private int    $timeout;
    private string $siteUrl;
    private string $siteName;

    /**
     * Fallback rotation of free vision-capable models, tried in order when
     * the caller hasn't configured AI_MODEL / AI_MODELS explicitly. If a
     * model comes back with HTTP 429 (quota exceeded), the next one in the
     * list is tried immediately for the same image — this mirrors the old
     * on-device DirectOpenRouterAnalyzer rotation logic, just moved server
     * side where the API key is safe.
     */
    private const DEFAULT_MODEL_ROTATION = [
        'nvidia/nemotron-nano-12b-v2-vl:free',
        'google/gemma-4-31b-it:free',
        'qwen/qwen2.5-vl-32b-instruct:free',
    ];

    public function __construct()
    {
        $this->apiKey   = config('ai.api_key') ?? '';
        $this->baseUrl  = config('ai.api_url') ?: 'https://openrouter.ai/api/v1';
        $this->models   = $this->resolveModels();
        $this->timeout  = config('ai.timeout') ?: 30;

        // OpenRouter uses these headers for rankings/analytics (optional but recommended).
        // Set AI_SITE_URL and AI_SITE_NAME in your .env file.
        $this->siteUrl  = config('ai.site_url')  ?: config('app.url', '');
        $this->siteName = config('ai.site_name') ?: config('app.name', '');

        if (empty($this->apiKey)) {
            throw AiProviderException::authenticationFailed();
        }
    }

    /**
     * Build the ordered list of models to try for each request.
     *
     * Priority:
     *   1. AI_MODELS in .env — comma-separated list, tried in that order.
     *   2. AI_MODEL in .env — single model, used alone (back-compat).
     *   3. DEFAULT_MODEL_ROTATION — built-in free-model fallback chain.
     *
     * @return string[]
     */
    private function resolveModels(): array
    {
        $configuredList = config('ai.models');
        if (! empty($configuredList)) {
            $models = is_array($configuredList)
                ? $configuredList
                : array_map('trim', explode(',', (string) $configuredList));

            $models = array_values(array_filter($models));
            if (! empty($models)) {
                return $models;
            }
        }

        $singleModel = config('ai.model');
        if (! empty($singleModel)) {
            return [$singleModel];
        }

        return self::DEFAULT_MODEL_ROTATION;
    }

    /**
     * {@inheritDoc}
     */
    public function analyze(string $imageBase64, string $prompt): array
    {
        $systemPrompt = <<<'SYSTEM'
You are an image analysis assistant. Analyze the provided image based on the user's prompt.
You MUST respond with valid JSON only — no markdown, no explanation outside the JSON.
The JSON must have exactly two keys:
  - "percentage" (integer 0–100): a confidence or relevance score.
  - "text" (string): your detailed analysis based on the user's prompt.
SYSTEM;

        $lastException      = null;
        $allFailedRateLimit = true;

        foreach ($this->models as $index => $model) {
            try {
                $result = $this->requestModel($model, $systemPrompt, $prompt, $imageBase64);
                Log::info('OpenRouter analysis succeeded', ['model' => $model]);

                return $result;
            } catch (AiProviderException $e) {
                $lastException = $e;

                if ($e->getStatusCode() === 429) {
                    Log::warning('OpenRouter model rate-limited, trying next in rotation', [
                        'model'    => $model,
                        'position' => $index + 1,
                        'total'    => count($this->models),
                    ]);

                    continue; // try the next model in the rotation
                }

                // Non-429 failure: log it but still try the remaining models,
                // since the failure may be specific to this one provider/model.
                $allFailedRateLimit = false;
                Log::error('OpenRouter model failed, trying next in rotation', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);
            } catch (RequestException $e) {
                $allFailedRateLimit = false;
                $lastException = AiProviderException::upstreamError($e->getMessage());
                Log::error('OpenRouter request failed', ['model' => $model, 'error' => $e->getMessage()]);
            } catch (\Throwable $e) {
                $allFailedRateLimit = false;
                $lastException = AiProviderException::upstreamError($e->getMessage());
                Log::error('OpenRouter unexpected error', ['model' => $model, 'error' => $e->getMessage()]);
            }
        }

        // Every model in the rotation failed. If they *all* failed specifically
        // because of rate limiting, say so clearly — that's actionable (wait,
        // add more models, or switch to a paid model). Otherwise surface the
        // last real error we hit.
        if ($allFailedRateLimit) {
            throw AiProviderException::rateLimited();
        }

        throw $lastException ?? AiProviderException::upstreamError('All configured models failed');
    }

    /**
     * Send a single analysis request to one specific model.
     *
     * @throws AiProviderException
     * @throws RequestException
     */
    private function requestModel(string $model, string $systemPrompt, string $prompt, string $imageBase64): array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/json',
                // OpenRouter-specific headers
                'HTTP-Referer'  => $this->siteUrl,
                'X-Title'       => $this->siteName,
            ])
            ->post("{$this->baseUrl}/chat/completions", [
                'model'       => $model,
                'max_tokens'  => 1024,
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role'    => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                            [
                                'type'      => 'image_url',
                                'image_url' => [
                                    'url' => "data:image/png;base64,{$imageBase64}",
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            $this->handleErrorResponse($response->status(), $response->body());
        }

        return $this->parseResponse($response->json());
    }

    /**
     * Parse the OpenRouter chat completion response into our standard format.
     *
     * The response structure is identical to OpenAI's Chat Completions API.
     *
     * @param  array|null  $body
     * @return array{percentage: int, text: string}
     *
     * @throws AiProviderException
     */
    private function parseResponse(?array $body): array
    {
        $content = $body['choices'][0]['message']['content'] ?? null;

        if (! $content) {
            throw AiProviderException::invalidResponse('No content in response');
        }

        // Strip possible markdown code fences
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($content));

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw AiProviderException::invalidResponse('Response is not valid JSON');
        }

        if (! isset($decoded['percentage'], $decoded['text'])) {
            throw AiProviderException::invalidResponse('Missing required keys: percentage, text');
        }

        return [
            'percentage' => (int) $decoded['percentage'],
            'text'       => (string) $decoded['text'],
        ];
    }

    /**
     * Map HTTP error status codes to appropriate exceptions.
     *
     * @throws AiProviderException
     */
    private function handleErrorResponse(int $status, string $body): never
    {
        Log::error('OpenRouter API error', ['status' => $status, 'body' => $body]);

        throw match (true) {
            $status === 400         => AiProviderException::invalidInput($this->extractErrorMessage($body)),
            $status === 401         => AiProviderException::authenticationFailed(),
            $status === 429         => AiProviderException::rateLimited(),
            $status >= 500          => AiProviderException::upstreamError("HTTP {$status}"),
            default                 => AiProviderException::upstreamError("HTTP {$status}: {$body}"),
        };
    }

    /**
     * Extract the raw error message from the response body.
     */
    private function extractErrorMessage(string $body): string
    {
        $data = json_decode($body, true);
        if (is_array($data)) {
            if (isset($data['error']['message'])) {
                $msg = $data['error']['message'];
                if ($msg === 'Provider returned error' && isset($data['error']['metadata']['raw'])) {
                    $raw = json_decode($data['error']['metadata']['raw'], true);
                    if (is_array($raw) && isset($raw['error']['message'])) {
                        return $raw['error']['message'];
                    }
                }
                return $msg;
            }
        }
        return $body;
    }
}

// namespace App\Services\Ai;

// use App\Contracts\AiProviderInterface;
// use App\Exceptions\AiProviderException;
// use Illuminate\Http\Client\RequestException;
// use Illuminate\Support\Facades\Http;
// use Illuminate\Support\Facades\Log;

// /**
//  * OpenAI-based AI provider (GPT-4o vision).
//  *
//  * Sends the image + prompt to the OpenAI Chat Completions API and parses
//  * the response into the standardised {percentage, text} format.
//  */
// class OpenAiProvider implements AiProviderInterface
// {
//     private string $apiKey;
//     private string $baseUrl;
//     private string $model;
//     private int    $timeout;

//     public function __construct()
//     {
//         $this->apiKey  = config('ai.api_key') ?? '';
//         $this->baseUrl = config('ai.api_url') ?: 'https://api.openai.com/v1';
//         $this->model   = config('ai.model')   ?: 'gpt-4o';
//         $this->timeout = config('ai.timeout') ?: 30;

//         if (empty($this->apiKey)) {
//             throw AiProviderException::authenticationFailed();
//         }
//     }

//     /**
//      * {@inheritDoc}
//      */
//     public function analyze(string $imageBase64, string $prompt): array
//     {
//         $systemPrompt = <<<'SYSTEM'
// You are an image analysis assistant. Analyze the provided image based on the user's prompt.
// You MUST respond with valid JSON only — no markdown, no explanation outside the JSON.
// The JSON must have exactly two keys:
//   - "percentage" (integer 0–100): a confidence or relevance score.
//   - "text" (string): your detailed analysis based on the user's prompt.
// SYSTEM;

//         try {
//             $response = Http::timeout($this->timeout)
//                 ->withHeaders([
//                     'Authorization' => "Bearer {$this->apiKey}",
//                     'Content-Type'  => 'application/json',
//                 ])
//                 ->post("{$this->baseUrl}/chat/completions", [
//                     'model'       => $this->model,
//                     'max_tokens'  => 1024,
//                     'messages'    => [
//                         [
//                             'role'    => 'system',
//                             'content' => $systemPrompt,
//                         ],
//                         [
//                             'role'    => 'user',
//                             'content' => [
//                                 [
//                                     'type' => 'text',
//                                     'text' => $prompt,
//                                 ],
//                                 [
//                                     'type'      => 'image_url',
//                                     'image_url' => [
//                                         'url' => "data:image/png;base64,{$imageBase64}",
//                                     ],
//                                 ],
//                             ],
//                         ],
//                     ],
//                 ]);

//             if ($response->failed()) {
//                 $this->handleErrorResponse($response->status(), $response->body());
//             }

//             return $this->parseResponse($response->json());

//         } catch (AiProviderException $e) {
//             throw $e; // re-throw our own exceptions
//         } catch (RequestException $e) {
//             Log::error('OpenAI request failed', ['error' => $e->getMessage()]);
//             throw AiProviderException::upstreamError($e->getMessage());
//         } catch (\Throwable $e) {
//             Log::error('OpenAI unexpected error', ['error' => $e->getMessage()]);
//             throw AiProviderException::upstreamError($e->getMessage());
//         }
//     }

//     /**
//      * Parse the OpenAI chat completion response into our standard format.
//      *
//      * @param  array|null  $body
//      * @return array{percentage: int, text: string}
//      *
//      * @throws AiProviderException
//      */
//     private function parseResponse(?array $body): array
//     {
//         $content = $body['choices'][0]['message']['content'] ?? null;

//         if (! $content) {
//             throw AiProviderException::invalidResponse('No content in response');
//         }

//         // Strip possible markdown code fences
//         $content = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($content));

//         $decoded = json_decode($content, true);

//         if (! is_array($decoded)) {
//             throw AiProviderException::invalidResponse('Response is not valid JSON');
//         }

//         if (! isset($decoded['percentage'], $decoded['text'])) {
//             throw AiProviderException::invalidResponse('Missing required keys: percentage, text');
//         }

//         return [
//             'percentage' => (int) $decoded['percentage'],
//             'text'       => (string) $decoded['text'],
//         ];
//     }

//     /**
//      * Map HTTP error status codes to appropriate exceptions.
//      *
//      * @throws AiProviderException
//      */
//     private function handleErrorResponse(int $status, string $body): never
//     {
//         Log::error('OpenAI API error', ['status' => $status, 'body' => $body]);

//         throw match (true) {
//             $status === 401         => AiProviderException::authenticationFailed(),
//             $status === 429         => AiProviderException::rateLimited(),
//             $status >= 500          => AiProviderException::upstreamError("HTTP {$status}"),
//             default                 => AiProviderException::upstreamError("HTTP {$status}: {$body}"),
//         };
//     }
// }