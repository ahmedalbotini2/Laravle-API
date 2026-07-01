<?php

namespace App\Console\Commands;

use App\Services\Ai\AiProviderFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Test the currently configured AI provider (stub / openrouter / etc).
 *
 * Usage:
 *   php artisan ai:test
 *   php artisan ai:test --image=/path/to/image.png
 *   php artisan ai:test --prompt="Describe what is in this image"
 */
class TestAiProvider extends Command
{
    protected $signature = 'ai:test
        {--image= : Path to a local image file to send. If omitted, a tiny generated test image is used.}
        {--prompt=Describe what you see in this image. : Prompt to send along with the image.}';

    protected $description = 'Send a test image + prompt through the configured AI provider and print the result.';

    public function handle(): int
    {
        $this->info('AI_PROVIDER = ' . config('ai.provider', 'stub'));
        $this->info('AI_MODEL    = ' . config('ai.model', '(default)'));
        $this->newLine();

        // 1. Get image (either from --image path or a tiny generated placeholder PNG)
        $imagePath = $this->option('image');

        if ($imagePath) {
            if (! File::exists($imagePath)) {
                $this->error("Image not found at: {$imagePath}");
                return self::FAILURE;
            }
            $imageBase64 = base64_encode(File::get($imagePath));
        } else {
            // 1x1 transparent PNG, base64 encoded — good enough to check
            // connectivity/auth/parsing without needing a real file.
            $imageBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
            $this->comment('No --image given, using a tiny 1x1 placeholder PNG instead.');
        }

        $prompt = $this->option('prompt');

        $this->newLine();
        $this->line("Prompt: {$prompt}");
        $this->newLine();

        // 2. Resolve provider from the factory (respects AI_PROVIDER in .env)
        try {
            $provider = AiProviderFactory::make();
        } catch (\Throwable $e) {
            $this->error('Failed to resolve provider: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->comment('Resolved provider class: ' . get_class($provider));
        $this->comment('Sending request...');
        $this->newLine();

        // 3. Call analyze() and time it
        $start = microtime(true);

        try {
            $result = $provider->analyze($imageBase64, $prompt);
        } catch (\Throwable $e) {
            $this->error('Provider threw an exception: ' . get_class($e));
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $elapsed = round((microtime(true) - $start) * 1000);

        // 4. Print result
        $this->info('✅ Success');
        $this->line("Time: {$elapsed} ms");
        $this->newLine();

        $this->line('Percentage: ' . ($result['percentage'] ?? 'N/A'));
        $this->line('Text:');
        $this->line($result['text'] ?? 'N/A');

        return self::SUCCESS;
    }
}
