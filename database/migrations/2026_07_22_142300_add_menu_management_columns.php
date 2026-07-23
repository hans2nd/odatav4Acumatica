<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('sort_order');
            $table->boolean('is_visible')->default(true)->after('is_active');
            $table->string('permission_key')->nullable()->after('is_visible');
            $table->enum('type', ['link', 'header', 'divider'])->default('link')->after('permission_key');
            $table->json('route_params')->nullable()->after('route_name');
            $table->string('badge')->nullable()->after('type');
            $table->boolean('open_in_new_tab')->default(false)->after('badge');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn([
                'is_active', 'is_visible', 'permission_key',
                'type', 'route_params', 'badge', 'open_in_new_tab',
            ]);
        });
    }
};
