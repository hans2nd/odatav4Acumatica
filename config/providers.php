<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Connection Providers Registry
    |--------------------------------------------------------------------------
    |
    | This registry maps connection types (keys) to their implementation class.
    | Each class must implement App\Contracts\Connections\ConnectionProviderInterface.
    |
    */

    'registry' => [
        'local' => [
            'display'      => 'Local Database (from .env)',
            'class'        => \App\Services\Connections\Providers\LocalEnvironmentProvider::class,
            'kind'         => 'database',
            'enabled'      => true,
            'capabilities' => ['read', 'write', 'schema'],
        ],
        'mysql' => [
            'display'      => 'MySQL',
            'class'        => \App\Services\Connections\Providers\MySqlProvider::class,
            'kind'         => 'database',
            'enabled'      => true,
            'capabilities' => ['read', 'write', 'schema'],
        ],
        'sqlsrv' => [
            'display'      => 'SQL Server',
            'class'        => \App\Services\Connections\Providers\SqlServerProvider::class,
            'kind'         => 'database',
            'enabled'      => true,
            'capabilities' => ['read', 'write', 'schema'],
        ],
        'firebird' => [
            'display'      => 'Firebird',
            'class'        => \App\Services\Connections\Providers\FirebirdProvider::class ?? null,
            'kind'         => 'database',
            'enabled'      => false,
            'capabilities' => ['read'],
        ],
        'acumatica' => [
            'display'             => 'Acumatica ERP',
            'class'               => \App\Services\Connections\Providers\AcumaticaProvider::class,
            'kind'                => 'api',
            'enabled'             => true,
            'capabilities'        => ['read', 'write', 'schema'],
            'supported_protocols' => ['odata_v3', 'odata_v4'],
            'default_protocol'    => 'odata_v4',
        ],
        'rest' => [
            'display'      => 'REST API',
            'class'        => \App\Services\Connections\Providers\RestApiProvider::class,
            'kind'         => 'api',
            'enabled'      => true,
            'capabilities' => ['read', 'write'],
        ],
    ],

    'aliases' => [
        'local_env' => 'local',
        'rest_api'  => 'rest',
        'odata'     => 'acumatica',
    ],

];
