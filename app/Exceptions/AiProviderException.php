<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Exception thrown when an AI provider encounters an error.
 *
 * This covers upstream API failures, rate limits, authentication errors,
 * malformed responses, and any other provider-specific issues.
 */
class AiProviderException extends Exception
{
    /**
     * The HTTP status code to return to the client.
     */
    protected int $statusCode;

    /**
     * Create a new AI provider exception.
     *
     * @param  string  $message     Human-readable error description.
     * @param  int     $statusCode  HTTP status code for the JSON response.
     * @param  int     $code        Internal error code.
     * @param  \Throwable|null  $previous  Previous exception for chaining.
     */
    public function __construct(
        string $message = 'AI provider encountered an error',
        int $statusCode = 502,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->statusCode = $statusCode;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Render the exception as a JSON response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code'    => $this->statusCode,
        ], $this->statusCode);
    }

    /* ------------------------------------------------------------------ */
    /* Named constructors for common failure scenarios                     */
    /* ------------------------------------------------------------------ */

    /**
     * The provider returned an unexpected or unparseable response.
     */
    public static function invalidResponse(string $details = ''): static
    {
        return new static(
            message: 'AI provider returned an invalid response' . ($details ? ": {$details}" : ''),
            statusCode: 502,
        );
    }

    /**
     * Authentication with the provider failed (bad / missing API key).
     */
    public static function authenticationFailed(): static
    {
        return new static(
            message: 'AI provider authentication failed — check your API key',
            statusCode: 401,
        );
    }

    /**
     * Rate limit exceeded on the provider side.
     */
    public static function rateLimited(): static
    {
        return new static(
            message: 'AI provider rate limit exceeded — please try again later',
            statusCode: 429,
        );
    }

    /**
     * A generic upstream error.
     */
    public static function upstreamError(string $reason = ''): static
    {
        return new static(
            message: 'AI provider upstream error' . ($reason ? ": {$reason}" : ''),
            statusCode: 502,
        );
    }

    /**
     * The input sent to the provider was invalid (e.g., bad image or prompt).
     */
    public static function invalidInput(string $reason = ''): static
    {
        return new static(
            message: 'Invalid input sent to AI provider' . ($reason ? ": {$reason}" : ''),
            statusCode: 400,
        );
    }
}