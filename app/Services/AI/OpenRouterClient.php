<?php

namespace App\Services\AI;

use App\Models\AiSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

class OpenRouterClient
{
    /**
     * Get a chat completion from OpenRouter.
     * 
     * @param array $messages Array of message arrays [['role' => 'user', 'content' => '...']]
     * @param bool $jsonMode If true, requests JSON output structure
     * @return array|null Returns the content of the assistant message or null on failure
     * @throws \Exception
     */
    public function getCompletion(array $messages, bool $jsonMode = false): ?string
    {
        $apiKey = trim(AiSetting::getConfig('openrouter_api_key', null, true) ?? '');
        
        if (!$apiKey) {
            throw new \Exception("OpenRouter API key is not configured.");
        }

        $baseUrl = AiSetting::getConfig('openrouter_base_url', 'https://openrouter.ai/api/v1');
        $model = AiSetting::getConfig('openrouter_model', 'openai/gpt-4o');
        $timeout = (int) AiSetting::getConfig('openrouter_timeout', 30);

        $routingService = app(OpenRouterRoutingService::class);
        $routingPayload = $routingService->resolveRoutingPayload($model);

        $payload = [
            'model' => $routingPayload['model'],
            'messages' => $messages,
        ];

        if (isset($routingPayload['provider'])) {
            $payload['provider'] = $routingPayload['provider'];
        }

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->withHeaders([
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => config('app.name'),
                ])
                ->post(rtrim($baseUrl, '/') . '/chat/completions', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                // Track usage (can be extracted to an Event or Listener later if needed)
                if (isset($data['usage'])) {
                    // Storing usage is handled by caller in ChatEDP flow
                    request()->attributes->set('llm_usage', $data['usage']);
                    request()->attributes->set('llm_model', $data['model'] ?? $model);
                }

                return $data['choices'][0]['message']['content'] ?? null;
            }

            if ($response->status() === 404) {
                $body = $response->body();
                \Illuminate\Support\Facades\Log::error("OpenRouter API 404 error: " . $body);
                
                if (str_contains($body, 'No endpoints') || str_contains($body, 'guardrail')) {
                    throw new \Exception("Tidak ada endpoint AI yang tersedia untuk model yang dipilih. Silakan periksa pengaturan privasi di akun OpenRouter Anda (https://openrouter.ai/settings/privacy) atau pilih model lain.", 404);
                }
                
                throw new \Exception("Model yang dipilih tidak ditemukan di OpenRouter.", 404);
            }

            if ($response->status() === 401) {
                \Illuminate\Support\Facades\Log::error("OpenRouter API Auth error: " . $response->body());
                throw new \Exception("OpenRouter Authentication Failed (401 User not found). Please verify the API key in AI Settings.", 401);
            }

            throw new \Exception("OpenRouter API error: " . $response->body(), $response->status());

        } catch (ConnectionException $e) {
            throw new \Exception("OpenRouter connection timeout or unreachable.", 504);
        }
    }
}
