<?php

namespace App\Contracts;

/**
 * Contract for AI provider implementations.
 *
 * All AI providers (OpenAI, Gemini, Stub, etc.) must implement this interface.
 * This ensures the controller and service layers remain decoupled from any
 * specific provider, allowing easy swapping via configuration.
 */
interface AiProviderInterface
{
    /**
     * Analyze an image with a text prompt via the AI provider.
     *
     * @param  string  $imageBase64  Base64-encoded image data.
     * @param  string  $prompt       The user's text prompt / instruction.
     * @return array{percentage: int, text: string}
     *
     * @throws \App\Exceptions\AiProviderException
     */
    public function analyze(string $imageBase64, string $prompt): array;
}
