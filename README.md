# AI Bridge — Laravel 12

A clean, scalable Laravel 12 API that acts as a bridge between your application and an AI provider. Send an image (Base64) and a text prompt → receive a structured JSON response with a `percentage` score and a `text` analysis.

---

## Quick Start

### Prerequisites

| Tool     | Version |
|----------|---------|
| PHP      | ≥ 8.2   |
| Composer | ≥ 2.x   |

### Installation

```bash
# Clone / download the project
cd Antigravity

# Install dependencies
composer install

# Copy environment file (already done if scaffolded fresh)
cp .env.example .env

# Generate app key (already done if scaffolded fresh)
php artisan key:generate

# Run migrations
php artisan migrate

# Start the dev server
php artisan serve
```

The API is now running at `http://127.0.0.1:8000`.

---

## Configuration

All AI-related settings live in `.env`:

```dotenv
AI_PROVIDER=stub          # stub | openai  (add more as needed)
AI_API_KEY=               # Your provider API key
AI_API_URL=               # Optional base URL override
AI_MODEL=                 # Optional model name 
AI_TIMEOUT=30             # HTTP timeout in seconds
```

> **Tip:** The default provider is `stub`, which returns fake data and requires no API key — perfect for development.

To switch to OpenRouter:

```dotenv
AI_PROVIDER=openrouter
AI_API_KEY=sk-your-key-here
AI_MODEL=nvidia
```

---

## API Endpoint

### `POST /api/ai/analyze`

| Field    | Type   | Required | Constraints              |
|----------|--------|----------|--------------------------|
| `image`  | string | ✅       | Base64-encoded, ≤ ~10 MB |
| `prompt` | string | ✅       | ≤ 5 000 characters       |

#### Example Request

```bash
curl -X POST http://127.0.0.1:8000/api/ai/analyze \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "image": "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==",
    "prompt": "Analyze this image and provide a quality percentage"
  }'
```

#### Success Response (200)

```json
{
  "percentage": 85,
  "text": "Based on the submitted command: \"Analyze this image and provide a quality percentage\" — the image has been analyzed successfully."
}
```

#### Validation Error (422)

```json
{
  "message": "An image is required. Provide a Base64-encoded image string.",
  "errors": {
    "image": [
      "An image is required. Provide a Base64-encoded image string."
    ]
  }
}
```

#### Provider Error (502)

```json
{
  "error": "AI provider error",
  "message": "AI provider upstream error: HTTP 500",
  "code": 502
}
```

---

## Console Output

When using `php artisan serve`, the AI response is printed to the console automatically:

```
[2026-06-22 19:20:25] local.INFO: AI Analysis Result {"percentage":88,"text":"Based on the submitted command: \"Analyze this image\" — the image has been analyzed successfully. This is a stub response for development and testing purposes. Replace the AI_PROVIDER in your .env file to use a real provider."}
```

---

## Project Structure (Custom Files)

```
app/
├── Contracts/
│   └── AiProviderInterface.php      # Provider contract
├── Exceptions/
│   └── AiProviderException.php      # Typed exception with named constructors
├── Http/
│   ├── Controllers/Api/
│   │   └── AiController.php         # POST /api/ai/analyze
│   └── Requests/
│       └── AiAnalyzeRequest.php     # Input validation
├── Providers/
│   └── AiServiceProvider.php        # Container bindings
└── Services/Ai/
    ├── AiProviderFactory.php        # Resolves provider from config
    ├── OpenAiProvider.php           # OpenAI GPT-4o vision adapter
    └── StubAiProvider.php           # Fake data (no API key needed)

config/
└── ai.php                           # AI_* env vars published here

tests/Feature/
└── AiAnalyzeTest.php                # 8 tests, 29 assertions
```

---

## Adding a New AI Provider

1. Create a class in `app/Services/Ai/` that implements `AiProviderInterface`:

```php
namespace App\Services\Ai;

use App\Contracts\AiProviderInterface;

class GeminiProvider implements AiProviderInterface
{
    public function analyze(string $imageBase64, string $prompt): array
    {
        // Call Gemini API…
        return ['percentage' => $score, 'text' => $analysis];
    }
}
```

2. Register it in `AiProviderFactory::$providers`:

```php
private static array $providers = [
    'stub'   => StubAiProvider::class,
    'openai' => OpenAiProvider::class,
    'gemini' => GeminiProvider::class,   // ← add this
];
```

3. Set `AI_PROVIDER=gemini` in `.env`.

---

## Running Tests

```bash
php artisan test
```

Expected output:

```
 PASS  Tests\Feature\AiAnalyzeTest
  ✓ it returns a successful analysis response
  ✓ it requires image field
  ✓ it requires prompt field
  ✓ it rejects empty payload
  ✓ it rejects non string image
  ✓ it rejects prompt exceeding max length
  ✓ it returns 502 when provider throws
  ✓ it returns deterministic stub output

  Tests:    9 passed (29 assertions)
```

---

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
