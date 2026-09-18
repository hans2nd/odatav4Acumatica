<?php

namespace Tests\Feature\AI;

use App\Models\User;
use App\Models\AiSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;
use App\Models\Role;

class OpenRouterConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The migration creates ai_settings.view/update/test permissions
    }

    private function createAdminUser()
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user->roles()->attach($role->id);
        
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return true;
        });
        
        return $user;
    }

    private function createNormalUser()
    {
        return User::factory()->create();
    }

    public function test_unauthorized_user_cannot_access_configuration()
    {
        $user = $this->createNormalUser();
        $this->actingAs($user)
             ->get(route('settings.ai.openrouter'))
             ->assertStatus(403);
    }

    public function test_authorized_administrator_can_access_configuration()
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin)
             ->get(route('settings.ai.openrouter'))
             ->assertStatus(200)
             ->assertSee('AI & Analytics Configuration', false);
    }

    public function test_api_key_is_stored_securely_and_encrypted()
    {
        $admin = $this->createAdminUser();
        $conn = \App\Models\Connection::create([
            'key' => 'analytics_conn_' . uniqid(),
            'name' => 'Analytics DB',
            'type' => 'mysql',
            'credentials' => []
        ]);
        $response = $this->actingAs($admin)->post(route('settings.ai.openrouter.update'), [
            'api_key' => 'sk-test-12345',
            'base_url' => 'https://openrouter.ai/api/v1',
            'model' => 'openai/gpt-4o',
            'timeout' => 30,
            'analytics_connection_id' => $conn->id,
            'provider_data_collection' => 'deny'
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        
        $setting = AiSetting::where('key', 'openrouter_api_key')->first();
        $this->assertNotNull($setting);
        $this->assertNotEquals('sk-test-12345', $setting->value);
        
        // Verify decryption works
        $this->assertEquals('sk-test-12345', AiSetting::getConfig('openrouter_api_key', null, true));
    }
    
    public function test_provider_only_array_is_stored_as_comma_separated_string()
    {
        $admin = $this->createAdminUser();
        $conn = \App\Models\Connection::create([
            'key' => 'analytics_conn_' . uniqid(),
            'name' => 'Analytics DB',
            'type' => 'mysql',
            'credentials' => []
        ]);
        $response = $this->actingAs($admin)->post(route('settings.ai.openrouter.update'), [
            'base_url' => 'https://openrouter.ai/api/v1',
            'model' => 'openai/gpt-4o',
            'timeout' => 30,
            'analytics_connection_id' => $conn->id,
            'provider_data_collection' => 'deny',
            'provider_only' => ['google-vertex', 'anthropic']
        ]);

        $response->assertSessionHasNoErrors();
        
        $this->assertEquals('google-vertex,anthropic', AiSetting::getConfig('openrouter_provider_only'));
    }

    public function test_connection_test_handles_success()
    {
        AiSetting::setConfig('openrouter_api_key', 'sk-valid', true);

        Http::fake([
            'openrouter.ai/*' => Http::response(['data' => []], 200)
        ]);

        $admin = $this->createAdminUser();
        
        $response = $this->actingAs($admin)->postJson(route('settings.ai.openrouter.test'));
        
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Connection to OpenRouter successful.'
                 ]);
    }

    public function test_connection_test_handles_authentication_error()
    {
        AiSetting::setConfig('openrouter_api_key', 'sk-invalid', true);

        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => 'Unauthorized'], 401)
        ]);

        $admin = $this->createAdminUser();
        
        $response = $this->actingAs($admin)->postJson(route('settings.ai.openrouter.test'));
        
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Authentication failed. Please check the API key.'
                 ]);
    }

    public function test_openrouter_client_sends_correct_zdr_payload()
    {
        AiSetting::setConfig('openrouter_api_key', 'sk-test', true);
        AiSetting::setConfig('openrouter_base_url', 'https://openrouter.ai/api/v1');
        AiSetting::setConfig('openrouter_model', 'google/gemini-3.5-flash-lite');
        AiSetting::setConfig('openrouter_provider_only', 'google-vertex');
        AiSetting::setConfig('openrouter_provider_allow_fallbacks', '0');
        AiSetting::setConfig('openrouter_provider_zdr', '1');
        AiSetting::setConfig('openrouter_provider_data_collection', 'deny');

        Http::fake([
            'openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => 'Response']]]], 200)
        ]);

        $client = new \App\Services\AI\OpenRouterClient();
        $client->getCompletion([['role' => 'user', 'content' => 'Hello']]);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $payload = $request->data();
            
            return $payload['model'] === 'google/gemini-3.5-flash-lite'
                && isset($payload['provider'])
                && $payload['provider']['order'] === ['google-vertex']
                && $payload['provider']['allow_fallbacks'] === false
                && $payload['provider']['zdr'] === true
                && $payload['provider']['data_collection'] === 'deny';
        });
    }
}
