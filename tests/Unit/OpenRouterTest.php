<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\AiSetting;
use App\Services\AI\OpenRouterClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use App\Http\Controllers\AiSettingsController;
use App\Models\User;

class OpenRouterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        AiSetting::setConfig('openrouter_api_key', 'test-sk-12345', true);
        AiSetting::setConfig('openrouter_base_url', 'https://openrouter.ai/api/v1');
        AiSetting::setConfig('openrouter_model', 'openai/gpt-4o');
        AiSetting::setConfig('openrouter_timeout', 30);
    }

    public function test_it_throws_safe_exception_on_guardrail_policy_restriction()
    {
        $rawError = json_encode([
            'error' => [
                'message' => 'No endpoints available matching your guardrail restrictions and data policy. Configure: https://openrouter.ai/settings/privacy',
                'code' => 404
            ]
        ]);

        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::response($rawError, 404)
        ]);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function($message) use ($rawError) {
                return str_contains($message, 'OpenRouter API technical error') && str_contains($message, $rawError);
            });

        $client = new OpenRouterClient();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("OpenRouter policy restriction:\nNo available model/provider endpoint matches the current OpenRouter privacy or guardrail policy.\nPlease review OpenRouter Privacy/Guardrail settings.");
        $this->expectExceptionCode(404);

        $client->getCompletion([['role' => 'user', 'content' => 'Hello']]);
    }

    public function test_it_returns_normal_response_on_success()
    {
        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::response([
                'id' => 'gen-123',
                'choices' => [
                    ['message' => ['content' => 'Hello there!']]
                ],
                'usage' => ['total_tokens' => 10],
                'model' => 'openai/gpt-4o'
            ], 200)
        ]);

        $client = new OpenRouterClient();
        
        // Mock request to satisfy attributes usage setting
        $request = new Request();
        app()->instance('request', $request);

        $result = $client->getCompletion([['role' => 'user', 'content' => 'Hello']]);

        $this->assertEquals('Hello there!', $result);
        $this->assertEquals(10, request()->attributes->get('llm_usage')['total_tokens']);
    }

    public function test_it_sends_correct_provider_routing_configuration()
    {
        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => function (\Illuminate\Http\Client\Request $request) {
                $payload = $request->data();
                
                // Verify provider routing config
                $this->assertArrayHasKey('provider', $payload);
                $this->assertArrayHasKey('only', $payload['provider']);
                $this->assertContains('google-vertex', $payload['provider']['only']);
                $this->assertNotContains('google-ai-studio', $payload['provider']['only']);
                $this->assertNotContains('google', $payload['provider']['only']);
                $this->assertFalse($payload['provider']['allow_fallbacks']);
                $this->assertTrue($payload['provider']['zdr']);
                $this->assertEquals('deny', $payload['provider']['data_collection']);
                
                return Http::response([
                    'id' => 'gen-123',
                    'choices' => [
                        ['message' => ['content' => 'Provider test passed']]
                    ],
                    'usage' => ['total_tokens' => 5],
                    'model' => 'google/gemini-3.5-flash-lite'
                ], 200);
            }
        ]);

        $client = new OpenRouterClient();
        $request = new Request();
        app()->instance('request', $request);

        $result = $client->getCompletion([['role' => 'user', 'content' => 'Test provider']]);
        
        $this->assertEquals('Provider test passed', $result);
    }



    public function test_admin_ui_test_connection_returns_safe_message_on_guardrail()
    {
        $rawError = json_encode([
            'error' => [
                'message' => 'No endpoints available matching your guardrail restrictions and data policy. Configure: https://openrouter.ai/settings/privacy',
                'code' => 404
            ]
        ]);

        Http::fake([
            'openrouter.ai/api/v1/models' => Http::response($rawError, 404)
        ]);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function($message) use ($rawError) {
                return str_contains($message, 'OpenRouter API technical error (Test Connection)') && str_contains($message, $rawError);
            });

        $controller = new AiSettingsController();
        
        // Mock user with permission
        $user = new User();
        $user->setAttribute('id', 1);
        
        // Mock permission check
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->with('ai_settings.test')->andReturn(true);
        
        $request = Request::create('/settings/ai/openrouter/test', 'POST');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $controller->testConnection($request);
        $data = $response->getData(true);

        $this->assertFalse($data['success']);
        $this->assertEquals('OpenRouter policy restriction: No available model/provider endpoint matches the current OpenRouter privacy or guardrail policy. Please review OpenRouter Privacy/Guardrail settings.', $data['message']);
        
        // Verify setting was updated
        $this->assertEquals('Policy Restriction', AiSetting::getConfig('openrouter_last_test_result'));
    }
}
