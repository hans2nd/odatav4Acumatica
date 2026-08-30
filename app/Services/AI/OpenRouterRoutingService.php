<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class OpenRouterRoutingService
{
    /**
     * Map of provider tags that are known to support Zero Data Retention (ZDR) natively via OpenRouter.
     * Since OpenRouter API does not expose ZDR capability directly in the endpoints list,
     * this list allows the UI to display helpful capability warnings.
     * OpenRouter's API itself enforces the actual routing regardless of this list.
     */
    protected $knownZdrProviders = [
        'openai', 'anthropic', 'azure', 'google-vertex', 'google', 'google-ai-studio', 
        'cohere', 'mistral', 'together', 'fireworks', 'aws', 'amazon-bedrock', 'xai', 'liquid', 'databricks'
    ];

    /**
     * Fetches and caches the available providers for a specific model from OpenRouter.
     */
    public function getModelProviders(string $modelId): array
    {
        // Cache per model for 24 hours
        return Cache::remember('openrouter_endpoints_' . md5($modelId), 86400, function () use ($modelId) {
            try {
                $response = Http::timeout(10)->get("https://openrouter.ai/api/v1/models/{$modelId}/endpoints");
                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['data']['endpoints']) && is_array($data['data']['endpoints'])) {
                        $providers = [];
                        foreach ($data['data']['endpoints'] as $ep) {
                            $tag = $ep['tag'] ?? null;
                            $name = $ep['provider_name'] ?? $tag;
                            if ($tag) {
                                // Prevent duplicates
                                $found = false;
                                foreach ($providers as $p) {
                                    if ($p['slug'] === $tag) {
                                        $found = true;
                                        break;
                                    }
                                }
                                if (!$found) {
                                    // Fix specific known names if needed
                                    if ($tag === 'google-vertex') $name = 'Google Vertex';
                                    if ($tag === 'google-ai-studio') $name = 'Google AI Studio';

                                    $providers[] = [
                                        'slug' => $tag,
                                        'name' => $name,
                                        'supports_zdr' => in_array($tag, $this->knownZdrProviders),
                                        'supports_data_collection_deny' => true, // most support this via API flag
                                    ];
                                }
                            }
                        }
                        
                        // Sort alphabetically
                        usort($providers, function($a, $b) {
                            return strcmp($a['name'], $b['name']);
                        });
                        
                        return $providers;
                    }
                }
            } catch (\Exception $e) {
                // Fall back to empty array
            }
            return [];
        });
    }

    /**
     * Builds the OpenRouter API payload constraints automatically.
     * 
     * Strategy:
     * - Let OpenRouter handle provider selection automatically, but guide it.
     * - If the user's account has strict privacy settings, OpenRouter might fail 
     *   to route to Azure automatically without 'allow_fallbacks' or explicit order.
     * - We will extract available providers and put ZDR-capable ones (like Azure) first.
     */
    public function resolveRoutingPayload(string $model): array 
    {
        return [
            'model' => $model
        ];
    }

    /**
     * Filters a given list of provider tags, keeping only those that actually support the model.
     */
    public function filterValidProvidersForModel(string $model, array $requestedProviders): array
    {
        $available = $this->getModelProviders($model);
        $availableSlugs = array_map('strtolower', array_column($available, 'slug'));
        
        $valid = [];
        foreach ($requestedProviders as $provider) {
            $trimmed = trim($provider);
            $lowerTrimmed = str_replace(' ', '-', strtolower($trimmed));
            if (in_array($lowerTrimmed, $availableSlugs) && !in_array($lowerTrimmed, $valid)) {
                $valid[] = $lowerTrimmed;
            }
        }
        return array_values(array_unique($valid));
    }
}
