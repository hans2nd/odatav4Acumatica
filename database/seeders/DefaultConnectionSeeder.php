<?php

namespace Database\Seeders;

use App\Models\Connection;
use Illuminate\Database\Seeder;

class DefaultConnectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $credentials = [
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
        ];

        Connection::updateOrCreate(
            ['key' => 'local_database'],
            [
                'name' => 'Local Database',
                'type' => env('DB_CONNECTION', 'mysql'),
                'credentials' => $credentials,
                'is_active' => true,
                'is_connected' => true,
                'is_default' => true,
                'is_system' => true,
                'read_only' => true,
                'deletable' => false,
                'editable' => false,
                'status' => 'Connected',
            ]
        );
    }
}
