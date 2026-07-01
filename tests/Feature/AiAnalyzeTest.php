<?php

namespace Tests\Feature;

use App\Contracts\AiProviderInterface;
use App\Exceptions\AiProviderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiAnalyzeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A tiny valid 1×1 PNG encoded as Base64.
     */
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    // ──────────────────────────────────────────────────────────────
    // Happy path
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_returns_a_successful_analysis_response(): void
    {
        $response = $this->postJson('/api/ai/analyze', [
            'image'  => self::TINY_PNG_BASE64,
            'prompt' => 'Analyze this image',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['percentage', 'text']);

        $data = $response->json();
        $this->assertIsInt($data['percentage']);
        $this->assertIsString($data['text']);
        $this->assertGreaterThanOrEqual(0, $data['percentage']);
        $this->assertLessThanOrEqual(100, $data['percentage']);
    }

    // ──────────────────────────────────────────────────────────────
    // Validation failures
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_requires_image_field(): void
    {
        $response = $this->postJson('/api/ai/analyze', [
            'prompt' => 'Some prompt',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['image']);
    }

    #[Test]
    public function it_requires_prompt_field(): void
    {
        $response = $this->postJson('/api/ai/analyze', [
            'image' => self::TINY_PNG_BASE64,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['prompt']);
    }

    #[Test]
    public function it_rejects_empty_payload(): void
    {
        $response = $this->postJson('/api/ai/analyze', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['image', 'prompt']);
    }

    #[Test]
    public function it_rejects_non_string_image(): void
    {
        $response = $this->postJson('/api/ai/analyze', [
            'image'  => 12345,
            'prompt' => 'Analyze',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['image']);
    }

    #[Test]
    public function it_rejects_invalid_base64_image(): void
    {
        // Special characters not in the base64 alphabet
        $response = $this->postJson('/api/ai/analyze', [
            'image'  => '!!!invalid-base64!!!',
            'prompt' => 'Analyze',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['image'])
                 ->assertJsonFragment([
                     'image' => ['The image must be a valid Base64-encoded string.']
                 ]);
    }

    #[Test]
    public function it_rejects_non_image_base64(): void
    {
        // 'dGVzdA==' is valid base64 of 'test', which is not a valid image
        $response = $this->postJson('/api/ai/analyze', [
            'image'  => 'dGVzdA==',
            'prompt' => 'Analyze',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['image'])
                 ->assertJsonFragment([
                     'image' => ['The image must be a valid JPEG, PNG, WebP, or GIF image.']
                 ]);
    }

    #[Test]
    public function it_rejects_prompt_exceeding_max_length(): void
    {
        $response = $this->postJson('/api/ai/analyze', [
            'image'  => self::TINY_PNG_BASE64,
            'prompt' => str_repeat('a', 5001),
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['prompt']);
    }

    // ──────────────────────────────────────────────────────────────
    // Provider error handling
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_returns_502_when_provider_throws(): void
    {
        $this->app->singleton(AiProviderInterface::class, function () {
            return new class implements AiProviderInterface {
                public function analyze(string $imageBase64, string $prompt): array
                {
                    throw AiProviderException::upstreamError('Simulated failure');
                }
            };
        });

        $response = $this->postJson('/api/ai/analyze', [
            'image'  => self::TINY_PNG_BASE64,
            'prompt' => 'Analyze this image',
        ]);

        $response->assertStatus(502)
                 ->assertJson([
                     'error' => 'AI provider error',
                     'code'  => 502,
                 ]);
    }

    #[Test]
    public function it_returns_deterministic_stub_output(): void
    {
        $payload = [
            'image'  => self::TINY_PNG_BASE64,
            'prompt' => 'Test prompt',
        ];

        $response1 = $this->postJson('/api/ai/analyze', $payload);
        $response2 = $this->postJson('/api/ai/analyze', $payload);

        $this->assertEquals($response1->json(), $response2->json());
    }
}
