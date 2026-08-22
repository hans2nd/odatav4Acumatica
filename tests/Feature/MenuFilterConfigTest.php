<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\QueryRepository;
use App\Models\Connection;
use App\Services\MenuFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuFilterConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_config_validation_rules()
    {
        $conn = Connection::create([
            'name' => 'test_conn',
            'db_connection' => 'mysql',
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_database' => 'test',
            'db_username' => 'root',
            'db_password' => ''
        ]);

        $queryRepo = QueryRepository::create([
            'name' => 'Test Query',
            'database_connection_id' => $conn->id,
            'sql_text' => 'SELECT id, name, created_at FROM users',
            'output_column_json' => json_encode(['id', 'name', 'created_at']),
        ]);

        // Validation for saving Menu via API
        $response = $this->postJson('/api/menus', [
            'title' => 'Test Menu',
            'type' => 'link',
            'route_name' => 'test.menu',
            'query_repository_id' => $queryRepo->id,
            'filter_config' => [
                ['field' => 'invalid_column', 'operator' => 'equals', 'type' => 'text']
            ]
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['filter_config']);
                 
        $response = $this->postJson('/api/menus', [
            'title' => 'Test Menu',
            'type' => 'link',
            'route_name' => 'test.menu',
            'query_repository_id' => $queryRepo->id,
            'filter_config' => [
                ['field' => 'name', 'operator' => 'invalid_op', 'type' => 'text']
            ]
        ]);
        
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['filter_config']);
    }

    public function test_required_validation_only_on_submit()
    {
        $service = new MenuFilterService();
        $query = DB::table('dummy');
        
        $filterConfig = [
            ['field' => 'name', 'operator' => 'equals', 'type' => 'text', 'required' => true]
        ];
        
        // Initial page load (no apply_filters)
        $request = new Request();
        // Should not throw exception
        $service->applyFilters($query, $filterConfig, $request);
        
        $this->assertTrue(true); // Reached without exception
        
        // Submit (with apply_filters)
        $requestSubmit = new Request(['apply_filters' => '1']);
        
        $this->expectException(ValidationException::class);
        $service->applyFilters($query, $filterConfig, $requestSubmit);
    }
    
    public function test_menu_filter_service_uses_connection_abstraction()
    {
        $service = new MenuFilterService();
        $query = DB::connection(config('database.default'))->table('dummy'); 
        
        $filterConfig = [
            ['field' => 'name', 'operator' => 'contains', 'type' => 'text']
        ];
        
        $request = new Request(['apply_filters' => '1', 'name' => 'test']);
        $service->applyFilters($query, $filterConfig, $request);
        
        $sql = strtolower($query->toSql());
        $this->assertStringContainsString('like', $sql);
        $this->assertStringContainsString('dummy', $sql);
    }
}
