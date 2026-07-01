<?php

namespace App\Services\Ai;

use App\Contracts\AiProviderInterface;

/**
 * Stub AI provider for development and testing.
 *
 * Returns deterministic fake data without making any external HTTP calls.
 * This is the default provider so the project works out of the box.
 */
class StubAiProvider implements AiProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function analyze(string $imageBase64, string $prompt): array
    {
        // Derive a deterministic but varied percentage from the input length
        // so different inputs produce different (but reproducible) results.
        $seed       = strlen($imageBase64) + strlen($prompt);
        $percentage = ($seed % 61) + 35; // range 35–95

        return [
            'percentage' => $percentage,
            'text'       => "Based on the submitted command: \"{$prompt}\" — "
                          . "the image has been analyzed successfully. "
                          . "This is a stub response for development and testing purposes. "
                          . "Replace the AI_PROVIDER in your .env file to use a real provider.",
        ];
    }
}
