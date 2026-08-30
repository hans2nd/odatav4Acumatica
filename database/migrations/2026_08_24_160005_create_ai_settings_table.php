<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable(); // Using text for potentially long encrypted strings
            $table->timestamps();
        });

        // Insert initial Granular Permissions for AI Settings
        $now = now();
        $permissions = [
            ['permission_key' => 'chatedp.access', 'description' => 'Access ChatEDP Analysis', 'created_at' => $now, 'updated_at' => $now],
            ['permission_key' => 'ai_settings.view', 'description' => 'View AI configuration settings', 'created_at' => $now, 'updated_at' => $now],
            ['permission_key' => 'ai_settings.update', 'description' => 'Update AI configuration settings', 'created_at' => $now, 'updated_at' => $now],
            ['permission_key' => 'ai_settings.test', 'description' => 'Test AI configuration settings', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('permissions')->insertOrIgnore($permissions);

        // Insert Default AI Settings
        $settings = [
            ['key' => 'openrouter_base_url', 'value' => 'https://openrouter.ai/api/v1', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'openrouter_model', 'value' => 'openai/gpt-4o', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'openrouter_timeout', 'value' => '30', 'created_at' => $now, 'updated_at' => $now],
        ];
        
        // Use DB::table to avoid model lifecycle overhead in migrations
        DB::table('ai_settings')->insertOrIgnore($settings);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
        DB::table('permissions')->whereIn('permission_key', [
            'chatedp.access',
            'ai_settings.view',
            'ai_settings.update',
            'ai_settings.test',
        ])->delete();
    }
};
