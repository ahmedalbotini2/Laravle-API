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
    private string $model;
    private int    $timeout;
    private string $siteUrl;
    private string $siteName;

    public function __construct()
    {
        $this->apiKey   = config('ai.api_key') ?? '';
        $this->baseUrl  = config('ai.api_url') ?: 'https://openrouter.ai/api/v1';
        $this->model    = config('ai.model')   ?: 'nvidia/nemotron-3-ultra-550b-a55b:free';
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

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type'  => 'application/json',
                    // OpenRouter-specific headers
                    'HTTP-Referer'  => $this->siteUrl,
                    'X-Title'       => $this->siteName,
                ])
                ->post("{$this->baseUrl}/chat/completions", [
                    'model'       => $this->model,
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
        } catch (AiProviderException $e) {
            throw $e; // re-throw our own exceptions
        } catch (RequestException $e) {
            Log::error('OpenRouter request failed', ['error' => $e->getMessage()]);
            throw AiProviderException::upstreamError($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('OpenRouter unexpected error', ['error' => $e->getMessage()]);
            throw AiProviderException::upstreamError($e->getMessage());
        }
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
