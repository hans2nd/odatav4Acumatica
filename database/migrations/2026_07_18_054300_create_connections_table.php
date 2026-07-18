<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 255);
            $table->string('type', 50); // odata, mysql, sqlsrv, rest_api
            $table->json('credentials')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Migrate existing JSON data if it exists
        $jsonPath = storage_path('app/connections.json');
        if (file_exists($jsonPath)) {
            $connections = json_decode(file_get_contents($jsonPath), true);
            if (is_array($connections)) {
                foreach ($connections as $key => $conn) {
                    \Illuminate\Support\Facades\DB::table('connections')->insert([
                        'key'         => $key,
                        'name'        => $conn['name'] ?? $key,
                        'type'        => $conn['type'] ?? 'mysql',
                        'credentials' => json_encode($conn['credentials'] ?? []),
                        'is_active'   => true,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
