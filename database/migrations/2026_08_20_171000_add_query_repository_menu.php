<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Menu;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $reportMenu = Menu::where('title', 'Report')->first();
        
        if ($reportMenu) {
            Menu::updateOrCreate([
                'route_name' => 'query-repository.index'
            ], [
                'parent_id' => $reportMenu->id,
                'title' => 'Query Repository',
                'icon' => 'database',
                'sort_order' => 40,
                'permission_key' => 'query_repository.view',
                'type' => 'link'
            ]);
            
            // Clear cache for sidebar
            \Illuminate\Support\Facades\Cache::forget('sidebar_menus');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Menu::where('route_name', 'query-repository.index')->delete();
        \Illuminate\Support\Facades\Cache::forget('sidebar_menus');
    }
};
