<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AiProviderInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiAnalyzeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Handles AI analysis API requests.
 *
 * POST /api/ai/analyze
 */
class AiController extends Controller
{
    public function __construct(
        private readonly AiProviderInterface $aiProvider,
    ) {}

    /**
     * Accept an image + prompt, forward to the AI provider, and return the result.
     */
    public function analyze(AiAnalyzeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->aiProvider->analyze(
            imageBase64: $validated['image'],
            prompt: $validated['prompt'],
        );

        // Print to console (visible in `php artisan serve` output)
        Log::channel('stderr')->info('AI Analysis Result', $result);

        // Wrap the provider result with a `success` flag so the Flutter/Android
        // client can branch on a single stable field instead of inferring
        // success from the HTTP status code alone.
        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }
}