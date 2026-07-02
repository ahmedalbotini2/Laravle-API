<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates incoming AI analysis requests.
 *
 * Expected JSON payload:
 * {
 *     "image":  "<base64-encoded image data>",
 *     "prompt": "Analyze this image…"
 * }
 */
class AiAnalyzeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // No auth guard for now — add Sanctum later if needed.
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image'  => [
                'required',
                'string',
                'max:15000000',
                function ($attribute, $value, $fail) {
                    $decoded = base64_decode($value, true);
                    if ($decoded === false) {
                        $fail('The image must be a valid Base64-encoded string.');
                        return;
                    }

                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->buffer($decoded);

                    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                    if (!in_array($mime, $allowedMimes)) {
                        $fail('The image must be a valid JPEG, PNG, WebP, or GIF image.');
                    }
                }
            ],
            'prompt' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required'  => 'An image is required. Provide a Base64-encoded image string.',
            'image.string'    => 'The image must be a Base64-encoded string.',
            'image.max'       => 'The image exceeds the maximum allowed size (~10 MB).',
            'prompt.required' => 'A text prompt is required.',
            'prompt.string'   => 'The prompt must be a string.',
            'prompt.max'      => 'The prompt must not exceed 5 000 characters.',
        ];
    }

    /**
     * Return validation failures as a stable `{success: false, ...}` JSON
     * envelope (instead of Laravel's default shape) so the Flutter/Android
     * client can rely on a single `success` field, while keeping the
     * original `message`/`errors` keys intact for backward compatibility.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422));
    }
}