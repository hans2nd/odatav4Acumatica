<?php

namespace Tests\Unit;

use App\Http\Controllers\QueryBuilderController;
use App\Services\ConnectionService;
use App\Services\Connections\ConnectionManager;
use Tests\TestCase;

class QueryBuilderControllerTest extends TestCase
{
    protected QueryBuilderController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $connService = $this->createMock(ConnectionService::class);
        $connManager = $this->createMock(ConnectionManager::class);
        $this->controller = new QueryBuilderController($connService, $connManager);
    }

    public function test_normalize_sql_for_sqlsrv_replaces_limit_with_top()
    {
        $sql = "SELECT grn.owner_code, grn.grn_no FROM grn WHERE grn.grn_date >= '2024-11-01' LIMIT 500";
        $normalized = $this->controller->normalizeSqlForDriver($sql, 'sqlsrv');

        $this->assertStringNotContainsString('LIMIT', $normalized);
        $this->assertStringStartsWith('SELECT TOP 500', $normalized);
    }

    public function test_normalize_sql_for_sqlsrv_distinct_with_top()
    {
        $sql = "SELECT DISTINCT owner_code FROM grn LIMIT 100";
        $normalized = $this->controller->normalizeSqlForDriver($sql, 'sqlsrv');

        $this->assertStringNotContainsString('LIMIT', $normalized);
        $this->assertStringStartsWith('SELECT DISTINCT TOP 100', $normalized);
    }

    public function test_normalize_sql_for_sqlsrv_limit_offset()
    {
        $sql = "SELECT owner_code FROM grn LIMIT 50 OFFSET 10";
        $normalized = $this->controller->normalizeSqlForDriver($sql, 'sqlsrv');

        $this->assertStringNotContainsString('LIMIT', $normalized);
        $this->assertStringContainsString('OFFSET 10 ROWS FETCH NEXT 50 ROWS ONLY', $normalized);
    }

    public function test_normalize_sql_for_mysql_keeps_limit()
    {
        $sql = "SELECT owner_code FROM grn LIMIT 500";
        $normalized = $this->controller->normalizeSqlForDriver($sql, 'mysql');

        $this->assertEquals($sql, $normalized);
    }
}
